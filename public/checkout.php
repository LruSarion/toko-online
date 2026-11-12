<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$ROOT = '../';
require_login();

$st = $pdo->prepare('SELECT * FROM pelanggan WHERE id = ?');
$st->execute([pelanggan_id()]);
$pel = $st->fetch();
if (!$pel) { $_SESSION = []; redirect('login.php'); }

$data = muat_keranjang($pdo);
foreach ($data['catatan'] as $c) { flash('info', $c); }
if (!$data['items']) { flash('info', 'Keranjangmu kosong. Pilih produk terlebih dahulu.'); redirect('cart.php'); }
$items = $data['items']; $sub = $data['subtotal'];

$opsiKirim = []; foreach ($APP['ongkir'] as $o) { $opsiKirim[$o['kode']] = $o; }
$metodeBayar = $APP['metode_bayar'];
$gratisMin = (int)$APP['gratis_ongkir_min'];

$f = [
    'nama' => $pel['nama'], 'email' => $pel['email'], 'no_hp' => (string)$pel['no_hp'], 'alamat' => (string)$pel['alamat'],
    'kota' => '', 'kodepos' => '', 'catatan' => '', 'kurir' => (string)array_key_first($opsiKirim), 'metode' => (string)array_key_first($metodeBayar), 'simpan' => '',
];
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    foreach (['nama', 'email', 'no_hp', 'alamat', 'kota', 'kodepos', 'catatan', 'kurir', 'metode', 'simpan'] as $k) { $f[$k] = post($k); }
    if ($f['nama'] === '') { $err['nama'] = 'Nama wajib diisi.'; }
    if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) { $err['email'] = 'Format email tidak valid.'; }
    if (strlen(preg_replace('/\D/', '', $f['no_hp'])) < 9) { $err['no_hp'] = 'Nomor HP tidak valid.'; }
    if (mb_strlen($f['alamat']) < 10) { $err['alamat'] = 'Tulis alamat selengkap mungkin (jalan, nomor, RT/RW, kecamatan).'; }
    if ($f['kota'] === '') { $err['kota'] = 'Kota/kabupaten wajib diisi.'; }
    if (!isset($opsiKirim[$f['kurir']])) { $err['kurir'] = 'Pilih opsi pengiriman.'; }
    if (!isset($metodeBayar[$f['metode']])) { $err['metode'] = 'Pilih metode pembayaran.'; }

    if (!$err) {
        try {
            $pdo->beginTransaction();
            $ids = array_map(function ($i) { return (int)$i['id']; }, $items);
            $s = $pdo->prepare('SELECT id, nama, harga, stok FROM produk WHERE aktif = 1 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') FOR UPDATE');
            $s->execute($ids);
            $kunci = [];
            foreach ($s->fetchAll() as $r) { $kunci[(int)$r['id']] = $r; }
            $total = 0; $baris = [];
            foreach ($items as $it) {
                $r = $kunci[(int)$it['id']] ?? null;
                if (!$r) { throw new AppError('“' . $it['nama'] . '” sudah tidak tersedia.'); }
                if ((int)$r['stok'] < (int)$it['jumlah']) { throw new AppError('Stok “' . $r['nama'] . '” tidak mencukupi (tersisa ' . (int)$r['stok'] . ').'); }
                $total += (int)$r['harga'] * (int)$it['jumlah'];
                $baris[] = [(int)$r['id'], (int)$it['jumlah'], (int)$r['harga']];
            }
            $kirim = $opsiKirim[$f['kurir']];
            $ongkir = hitung_ongkir($kirim, $total);
            $alamatLengkap = $f['alamat'] . "\n" . $f['kota'] . ($f['kodepos'] !== '' ? ', ' . $f['kodepos'] : '');

            $pdo->prepare('INSERT INTO pesanan (pelanggan_id, nama_pelanggan, email, no_hp, alamat, total_harga, ongkir, kurir, metode_bayar, catatan) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([pelanggan_id(), $f['nama'], $f['email'] ?: null, $f['no_hp'], $alamatLengkap, $total + $ongkir, $ongkir, $kirim['nama'], $f['metode'], $f['catatan'] ?: null]);
            $orderId = (int)$pdo->lastInsertId();

            $det = $pdo->prepare('INSERT INTO detail_pesanan (pesanan_id, produk_id, jumlah, harga) VALUES (?,?,?,?)');
            $stk = $pdo->prepare('UPDATE produk SET stok = stok - ? WHERE id = ? AND stok >= ?');
            foreach ($baris as $b) {
                $det->execute([$orderId, $b[0], $b[1], $b[2]]);
                $stk->execute([$b[1], $b[0], $b[1]]);
                if ($stk->rowCount() === 0) { throw new AppError('Stok produk berubah saat diproses. Silakan coba lagi.'); }
            }
            $pdo->commit();

            $_SESSION['cart'] = [];
            $_SESSION['last_order_id'] = $orderId;
            if ($f['simpan'] === '1') {
                try { $pdo->prepare('UPDATE pelanggan SET no_hp = ?, alamat = ? WHERE id = ?')->execute([$f['no_hp'], $f['alamat'], pelanggan_id()]); }
                catch (Throwable $e2) { error_log('Simpan profil gagal: ' . $e2->getMessage()); }
            }
            redirect('pesanan.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if ($e instanceof AppError) { $err['umum'] = $e->getMessage(); }
            else { error_log('Checkout gagal: ' . $e->getMessage()); $err['umum'] = 'Pesanan belum berhasil dibuat. Silakan coba lagi sebentar lagi.'; }
        }
    }
}

$ongkirAwal = hitung_ongkir($opsiKirim[$f['kurir']] ?? ['biaya' => 0], $sub);
$page = ['title' => 'Checkout', 'noindex' => true, 'nav' => 'cart'];
require ROOT_PATH . '/includes/store_header.php';
$fe = function (string $k) use ($err) { return isset($err[$k]) ? '<div class="err">' . e($err[$k]) . '</div>' : ''; };
?>
<div class="wrap">
  <div class="steps"><span class="done"><b>1</b>Keranjang</span><hr><span class="on"><b>2</b>Checkout</span><hr><span><b>3</b>Selesai</span></div>
  <div class="page-h"><h1>Checkout</h1></div>

  <form method="post" class="cols" novalidate data-once>
    <?= csrf_field() ?>
    <div class="stack">
      <?php if (isset($err['umum'])): ?><div class="alert alert-error"><?= e($err['umum']) ?></div><?php endif; ?>

      <section class="card">
        <h2>1. Data penerima</h2>
        <div class="grid2">
          <div class="field <?= isset($err['nama']) ? 'has-err' : '' ?>"><label for="nama">Nama lengkap</label><input class="input" id="nama" name="nama" value="<?= e($f['nama']) ?>" autocomplete="name" required><?= $fe('nama') ?></div>
          <div class="field <?= isset($err['no_hp']) ? 'has-err' : '' ?>"><label for="no_hp">Nomor HP / WhatsApp</label><input class="input" id="no_hp" name="no_hp" value="<?= e($f['no_hp']) ?>" inputmode="tel" autocomplete="tel" required><?= $fe('no_hp') ?></div>
        </div>
        <div class="field <?= isset($err['email']) ? 'has-err' : '' ?>" style="margin-bottom:0"><label for="email">Email <span class="muted">(opsional)</span></label><input class="input" id="email" type="email" name="email" value="<?= e($f['email']) ?>" autocomplete="email"><?= $fe('email') ?></div>
      </section>

      <section class="card">
        <h2>2. Alamat pengiriman</h2>
        <div class="field <?= isset($err['alamat']) ? 'has-err' : '' ?>"><label for="alamat">Alamat lengkap</label><textarea class="textarea" id="alamat" name="alamat" placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan, kecamatan" required><?= e($f['alamat']) ?></textarea><?= $fe('alamat') ?></div>
        <div class="grid2">
          <div class="field <?= isset($err['kota']) ? 'has-err' : '' ?>"><label for="kota">Kota / Kabupaten</label><input class="input" id="kota" name="kota" value="<?= e($f['kota']) ?>" required><?= $fe('kota') ?></div>
          <div class="field"><label for="kodepos">Kode pos <span class="muted">(opsional)</span></label><input class="input" id="kodepos" name="kodepos" value="<?= e($f['kodepos']) ?>" inputmode="numeric" maxlength="6"></div>
        </div>
        <label class="check"><input type="checkbox" name="simpan" value="1" <?= $f['simpan'] === '1' ? 'checked' : '' ?>> Simpan nomor HP &amp; alamat ini ke profilku</label>
      </section>

      <section class="card">
        <h2>3. Pengiriman</h2>
        <div class="radio-cards">
          <?php foreach ($opsiKirim as $kode => $o): $b = hitung_ongkir($o, $sub); ?>
          <label class="rc"><input type="radio" name="kurir" value="<?= e($kode) ?>" data-biaya="<?= (int)$o['biaya'] ?>" <?= $f['kurir'] === $kode ? 'checked' : '' ?>>
            <span class="rt"><b><?= e($o['nama']) ?></b><span><?= e($o['estimasi']) ?></span></span>
            <span class="rp"><?= $b === 0 ? 'Gratis' : rupiah($b) ?></span></label>
          <?php endforeach; ?>
        </div>
        <?= $fe('kurir') ?>
      </section>

      <section class="card">
        <h2>4. Pembayaran</h2>
        <div class="radio-cards">
          <?php foreach ($metodeBayar as $nama => $ket): ?>
          <label class="rc"><input type="radio" name="metode" value="<?= e($nama) ?>" <?= $f['metode'] === $nama ? 'checked' : '' ?>>
            <span class="rt"><b><?= e($nama) ?></b><span><?= e($ket) ?></span></span></label>
          <?php endforeach; ?>
        </div>
        <?= $fe('metode') ?>
        <div class="field" style="margin:18px 0 0"><label for="catatan">Catatan untuk penjual <span class="muted">(opsional)</span></label><textarea class="textarea" id="catatan" name="catatan" style="min-height:70px" placeholder="Mis. warna, jam pengiriman, atau permintaan khusus"><?= e($f['catatan']) ?></textarea></div>
      </section>
    </div>

    <aside class="card summary" id="sum" data-sub="<?= $sub ?>" data-free="<?= $gratisMin ?>">
      <h2>Ringkasan pesanan</h2>
      <?php foreach ($items as $it): $im = produk_img($it['gambar'], $ROOT, 'thumb'); ?>
        <div class="mini">
          <div class="th"><?php if ($im): ?><img src="<?= e($im) ?>" alt=""><?php endif; ?><em><?= (int)$it['jumlah'] ?></em></div>
          <div class="nm"><?= e($it['nama']) ?></div><div class="pr"><?= rupiah($it['subtotal']) ?></div>
        </div>
      <?php endforeach; ?>
      <div style="border-top:1px solid var(--line);margin-top:10px;padding-top:10px">
        <div class="sum-row"><span class="muted">Subtotal</span><span><?= rupiah($sub) ?></span></div>
        <div class="sum-row"><span class="muted">Ongkos kirim</span><span id="sum-ongkir"><?= $ongkirAwal === 0 ? 'Gratis' : rupiah($ongkirAwal) ?></span></div>
        <div class="sum-row total"><span>Total bayar</span><span id="sum-total"><?= rupiah($sub + $ongkirAwal) ?></span></div>
      </div>
      <div class="poin-note"><?= icon('gift', 18) ?> Kamu akan dapat <?= hitungPoin($sub) ?> poin setelah pesanan selesai.</div>
      <button class="btn btn-primary btn-block btn-lg" style="margin-top:18px" type="submit">Buat pesanan</button>
      <a class="btn btn-ghost btn-block" style="margin-top:10px" href="cart.php">Kembali ke keranjang</a>
      <div class="secure"><?= icon('lock', 15) ?> Harga &amp; stok diverifikasi ulang saat pesanan dibuat</div>
    </aside>
  </form>
</div>
<?php require ROOT_PATH . '/includes/store_footer.php'; ?>
