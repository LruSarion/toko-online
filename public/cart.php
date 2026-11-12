<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$ROOT = '../';
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) { $_SESSION['cart'] = []; }

// ---------- Aksi keranjang ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = post('action'); $pid = (int)post('produk_id'); $jml = max(1, (int)post('jumlah', '1'));
    $to = 'cart.php';
    if ($pid > 0 && in_array($aksi, ['tambah', 'beli', 'kurangi', 'hapus', 'update'], true)) {
        $st = $pdo->prepare('SELECT id, nama, stok FROM produk WHERE id = ? AND aktif = 1');
        $st->execute([$pid]);
        $pr = $st->fetch();
        if (!$pr) {
            unset($_SESSION['cart'][$pid]);
            flash('error', 'Produk tidak tersedia lagi.');
        } else {
            $stok = (int)$pr['stok'];
            $now = (int)($_SESSION['cart'][$pid] ?? 0);
            if ($aksi === 'tambah' || $aksi === 'beli') {
                if ($stok < 1) { flash('error', '“' . $pr['nama'] . '” sedang habis.'); }
                elseif ($now >= $stok) {
                    flash('error', 'Jumlah di keranjang sudah mencapai stok tersedia (' . $stok . ').');
                    if ($aksi === 'beli') { $to = 'checkout.php'; }
                } else {
                    $_SESSION['cart'][$pid] = min($stok, $now + $jml);
                    if ($aksi === 'beli') { $to = 'checkout.php'; }
                    else {
                        flash('success', '“' . $pr['nama'] . '” ditambahkan ke keranjang.', ['href' => 'cart.php', 'label' => 'Lihat keranjang']);
                        $to = safe_next(post('back'), 'cart.php');
                    }
                }
            } elseif ($aksi === 'kurangi') {
                if ($now > 1) { $_SESSION['cart'][$pid] = $now - 1; } else { unset($_SESSION['cart'][$pid]); }
            } elseif ($aksi === 'hapus') {
                unset($_SESSION['cart'][$pid]);
                flash('info', '“' . $pr['nama'] . '” dihapus dari keranjang.');
            } else { // update
                $n = max(0, (int)post('jumlah', '0'));
                if ($n === 0) { unset($_SESSION['cart'][$pid]); } else { $_SESSION['cart'][$pid] = min($n, max(1, $stok)); }
            }
        }
    }
    redirect($to);
}

// ---------- Tampilan ----------
$data = muat_keranjang($pdo);
foreach ($data['catatan'] as $c) { flash('info', $c); }
$items = $data['items']; $sub = $data['subtotal'];
$gratisMin = (int)$APP['gratis_ongkir_min'];
$rekomendasi = [];
if (!$items) { $rekomendasi = $pdo->query('SELECT * FROM produk WHERE aktif = 1 AND stok > 0 ORDER BY unggulan DESC, id DESC LIMIT 4')->fetchAll(); }

$page = ['title' => 'Keranjang belanja', 'noindex' => true, 'nav' => 'cart'];
require ROOT_PATH . '/includes/store_header.php';
?>
<div class="wrap">
  <div class="page-h"><h1>Keranjang belanja</h1><?php if ($items): ?><p><?= cart_count() ?> item siap di-checkout</p><?php endif; ?></div>

  <?php if ($items): ?>
  <div class="cols">
    <div class="card">
      <?php foreach ($items as $it): $im = produk_img($it['gambar'], $ROOT, 'thumb'); $stok = (int)$it['stok']; ?>
      <div class="citem">
        <a class="th" href="product.php?id=<?= (int)$it['id'] ?>"><?php if ($im): ?><img src="<?= e($im) ?>" alt="" loading="lazy"><?php else: echo icon('package', 30); endif; ?></a>
        <div>
          <h3><a href="product.php?id=<?= (int)$it['id'] ?>"><?= e($it['nama']) ?></a></h3>
          <div class="sub"><?= rupiah($it['harga']) ?> / unit · stok <?= $stok ?></div>
          <div class="ctl">
            <form method="post" class="cart-qty">
              <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="produk_id" value="<?= (int)$it['id'] ?>">
              <div class="qty">
                <button type="button" data-step="-1" aria-label="Kurangi"><?= icon('minus', 14) ?></button>
                <input type="number" name="jumlah" value="<?= (int)$it['jumlah'] ?>" min="1" max="<?= $stok ?>" aria-label="Jumlah <?= e($it['nama']) ?>">
                <button type="button" data-step="1" aria-label="Tambah"><?= icon('plus', 14) ?></button>
              </div>
            </form>
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="action" value="hapus"><input type="hidden" name="produk_id" value="<?= (int)$it['id'] ?>">
              <button class="link" style="color:var(--bad);font-weight:500;display:flex;gap:6px;align-items:center"><?= icon('trash', 16) ?> Hapus</button>
            </form>
          </div>
        </div>
        <div class="tot"><?= rupiah($it['subtotal']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <aside class="card summary">
      <h2>Ringkasan belanja</h2>
      <?php if ($gratisMin > 0): ?>
        <div class="free-bar">
          <?php if ($sub >= $gratisMin): ?><b>Selamat! Kamu dapat gratis ongkir.</b>
          <?php else: ?>Belanja <b><?= rupiah($gratisMin - $sub) ?></b> lagi untuk gratis ongkir.<?php endif; ?>
          <div class="bar"><i style="width:<?= min(100, (int)round($sub / $gratisMin * 100)) ?>%"></i></div>
        </div>
      <?php endif; ?>
      <div class="sum-row"><span class="muted">Subtotal (<?= cart_count() ?> item)</span><span><?= rupiah($sub) ?></span></div>
      <div class="sum-row"><span class="muted">Ongkos kirim</span><span class="muted">Dihitung di checkout</span></div>
      <div class="sum-row total"><span>Total</span><span><?= rupiah($sub) ?></span></div>
      <?php if (pelanggan_id()): ?>
        <div class="poin-note"><?= icon('gift', 18) ?> Kamu akan dapat <?= hitungPoin($sub) ?> poin dari pesanan ini.</div>
      <?php endif; ?>
      <a class="btn btn-primary btn-block btn-lg" style="margin-top:18px" href="checkout.php">Lanjut ke checkout</a>
      <a class="btn btn-ghost btn-block" style="margin-top:10px" href="index.php#produk">Lanjut belanja</a>
      <div class="secure"><?= icon('lock', 15) ?> Checkout aman &amp; data terlindungi</div>
    </aside>
  </div>
  <?php else: ?>
  <div class="empty" style="margin-bottom:32px">
    <div class="ic"><?= icon('cart', 32) ?></div>
    <h2>Keranjangmu masih kosong</h2>
    <p>Yuk, pilih produk favoritmu dan mulai belanja.</p>
    <a class="btn btn-primary btn-lg" href="index.php#produk">Mulai belanja</a>
  </div>
  <?php if ($rekomendasi): ?>
    <div class="sec-head"><div><h2 style="font-size:24px">Mungkin kamu suka</h2></div></div>
    <div class="grid" style="margin-bottom:24px"><?php foreach ($rekomendasi as $r) { echo product_card($r, $ROOT); } ?></div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php require ROOT_PATH . '/includes/store_footer.php'; ?>
