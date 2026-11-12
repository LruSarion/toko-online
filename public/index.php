<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$ROOT = '../';

// ---------- Filter ----------
$q      = qs('q', qs('search'));
$kat    = qs('kategori');
$urut   = qs('urut', 'terbaru');
$min    = max(0, (int)qs('min'));
$max    = max(0, (int)qs('max'));
$stokOk = qs('stok') === '1';
$hal    = max(1, (int)qs('page', '1'));
$urutan = [
    'terbaru'  => ['Terbaru', 'id DESC'],
    'terlaris' => ['Terlaris', 'terjual DESC, id DESC'],
    'termurah' => ['Harga terendah', 'harga ASC, id DESC'],
    'termahal' => ['Harga tertinggi', 'harga DESC, id DESC'],
    'nama'     => ['Nama A–Z', 'nama ASC'],
];
if (!isset($urutan[$urut])) { $urut = 'terbaru'; }
$filtering = ($q !== '' || $kat !== '' || $min > 0 || $max > 0 || $stokOk || $urut !== 'terbaru' || $hal > 1);

$where = ['aktif = 1']; $par = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(nama LIKE ? OR deskripsi LIKE ? OR kategori LIKE ?)';
    array_push($par, $like, $like, $like);
}
if ($kat !== '') { $where[] = 'kategori = ?'; $par[] = $kat; }
if ($min > 0) { $where[] = 'harga >= ?'; $par[] = $min; }
if ($max > 0) { $where[] = 'harga <= ?'; $par[] = $max; }
if ($stokOk) { $where[] = 'stok > 0'; }
$W = implode(' AND ', $where);

$total = (int)scalar($pdo, "SELECT COUNT(*) FROM produk WHERE $W", $par);
$perHal = max(4, (int)$APP['per_halaman']);
$halaman = min($hal, max(1, (int)ceil($total / $perHal)));
$offset = ($halaman - 1) * $perHal;
$st = $pdo->prepare(
    "SELECT produk.*, (SELECT COALESCE(SUM(dp.jumlah), 0) FROM detail_pesanan dp JOIN pesanan ps ON ps.id = dp.pesanan_id
                       WHERE dp.produk_id = produk.id AND ps.status <> 'Dibatalkan') AS terjual
     FROM produk WHERE $W ORDER BY {$urutan[$urut][1]} LIMIT $perHal OFFSET $offset"
);
$st->execute($par);
$produk = $st->fetchAll();

// Produk unggulan (hanya di tampilan awal tanpa filter)
$unggulan = []; $heroItems = [];
if (!$filtering) {
    $unggulan = $pdo->query('SELECT * FROM produk WHERE aktif = 1 AND unggulan = 1 AND stok > 0 ORDER BY id DESC LIMIT 4')->fetchAll();
    $heroItems = array_slice($unggulan ?: $produk, 0, 3);
}

// Query yang dipertahankan di pagination & chip
$keep = array_filter(['q' => $q, 'kategori' => $kat, 'urut' => $urut !== 'terbaru' ? $urut : '', 'min' => $min ?: '', 'max' => $max ?: '', 'stok' => $stokOk ? '1' : ''], 'strlen');


$page = [
    'nav'   => 'home',
    'title' => $kat !== ''
        ? $kat
        : ($q !== ''
            ? 'Cari “' . $q . '”'
            : 'Toko Online Gadget & Elektronik'),
];

if ($kat === '' && $q === '') {
    $page['desc'] = 'Belanja laptop, monitor, smartwatch, dan gadget pilihan di Tokko Kulaku. Temukan produk sesuai kebutuhan dan pesan dengan mudah secara online.';
}

require ROOT_PATH . '/includes/store_header.php';
?>

<?php if (!$filtering): ?>
<section class="hero">
  <div class="wrap">
    <div>
      <span class="eyebrow">Gadget &amp; elektronik pilihan</span>
        <h1>Temukan Kebutuhanmu<em> Semua ada </em>di Sini.</h1>
      <p>Jelajahi pilihan gadget dan elektronik untuk menemani aktivitasmu. Temukan produk yang sesuai kebutuhan, lakukan pemesanan dengan mudah, dan pantau status pesanan langsung dari akun Tokko Kulaku.         </p>
      <div class="hero-cta">
        <a class="btn btn-gold btn-lg" href="#produk">Belanja sekarang</a>
        <a class="btn btn-ghost btn-lg" href="<?= e(wa_link('Halo admin, saya butuh rekomendasi produk.')) ?>" target="_blank" rel="noopener">Minta rekomendasi</a>
      </div>
    </div>
    <?php if (!empty($heroItems)): ?>
    <div class="hero-cards">
      <?php foreach ($heroItems as $h): $hi = produk_img($h['gambar'], $ROOT, 'thumb'); ?>
        <a class="hcard" href="product.php?id=<?= (int)$h['id'] ?>">
          <div class="himg"><?php if ($hi): ?><img src="<?= e($hi) ?>" alt="" loading="eager"><?php else: echo icon('package', 40); endif; ?></div>
          <b><?= e($h['nama']) ?></b><small><?= rupiah($h['harga']) ?></small>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="trust">
  <div class="wrap">
    <div class="trust-i"><span class="ic"><?= icon('truck', 22) ?></span><div><b>Pilihan pengiriman</b><span>Pilih opsi kirim yang cocok saat checkout</span></div></div>
    <div class="trust-i"><span class="ic"><?= icon('shield', 22) ?></span><div><b>Transaksi terjamin</b><span>Pesanan dikonfirmasi &amp; dipantau admin</span></div></div>
    <div class="trust-i"><span class="ic"><?= icon('package', 22) ?></span><div><b>Stok real-time</b><span>Stok diperbarui otomatis di setiap pesanan</span></div></div>
    <div class="trust-i"><span class="ic"><?= icon('gift', 22) ?></span><div><b>Kumpulkan poin</b><span>Setiap Rp10.000 belanja = 1 poin</span></div></div>
  </div>
</section>

<?php if ($navKategori): ?>
<section class="section"><div class="wrap">
  <div class="sec-head"><div><h2>Belanja per kategori</h2><p>Temukan perangkat yang kamu cari dengan lebih cepat.</p></div></div>
  <div class="cats">
    <?php foreach ($navKategori as $k): ?>
      <a class="cat" href="index.php?kategori=<?= urlencode($k['kategori']) ?>#produk">
        <span class="ic"><?= icon(kategori_icon($k['kategori']), 26) ?></span>
        <b><?= e($k['kategori']) ?></b><span><?= (int)$k['n'] ?> produk</span>
      </a>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<?php if ($unggulan): ?>
<section class="section"><div class="wrap">
  <div class="sec-head"><div><h2>Produk unggulan</h2><p>Pilihan favorit dari kami.</p></div></div>
  <div class="grid">
    <?php foreach ($unggulan as $p) { echo product_card($p, $ROOT); } ?>
  </div>
</div></section>
<?php endif; ?>
<?php else: ?>
<div class="wrap"><div class="crumbs"><a href="index.php">Beranda</a> <?= icon('right', 14) ?> <span><?= $kat !== '' ? e($kat) : 'Hasil pencarian' ?></span></div></div>
<?php endif; ?>

<section class="section" id="produk"><div class="wrap">
  <div class="sec-head">
    <div>
      <h2><?= $kat !== '' ? e($kat) : ($q !== '' ? 'Hasil untuk “' . e($q) . '”' : 'Semua produk') ?></h2>
      <?php if (!$filtering): ?><p>Pilih produk yang ingin kamu beli.</p><?php endif; ?>
    </div>
  </div>

  <form method="get" action="index.php" class="shop">
    <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
    <aside class="filters">
      <details class="fbox">
        <summary><span><?= icon('filter', 16) ?> Filter</span><?= icon('down', 16) ?></summary>
        <div class="fbody">
          <div class="fgroup">
            <h4>Kategori</h4>
            <label class="opt"><input type="radio" name="kategori" value="" <?= $kat === '' ? 'checked' : '' ?> data-autosubmit> Semua</label>
            <?php foreach ($navKategori as $k): ?>
              <label class="opt"><input type="radio" name="kategori" value="<?= e($k['kategori']) ?>" <?= $kat === $k['kategori'] ? 'checked' : '' ?> data-autosubmit> <?= e($k['kategori']) ?> <small><?= (int)$k['n'] ?></small></label>
            <?php endforeach; ?>
          </div>
          <div class="fgroup">
            <h4>Rentang harga (Rp)</h4>
            <div class="range">
              <input class="input" type="number" name="min" min="0" step="1000" placeholder="Min" value="<?= $min ?: '' ?>" aria-label="Harga minimum">
              <input class="input" type="number" name="max" min="0" step="1000" placeholder="Maks" value="<?= $max ?: '' ?>" aria-label="Harga maksimum">
            </div>
          </div>
          <div class="fgroup">
            <label class="opt"><input type="checkbox" name="stok" value="1" <?= $stokOk ? 'checked' : '' ?> data-autosubmit> Hanya yang tersedia</label>
          </div>
          <button class="btn btn-primary btn-block btn-sm" type="submit">Terapkan filter</button>
          <?php if ($filtering): ?><a class="btn btn-ghost btn-block btn-sm" style="margin-top:8px" href="index.php#produk">Reset</a><?php endif; ?>
        </div>
      </details>
    </aside>

    <div>
      <div class="toolbar">
        <div class="res"><?= number_format($total, 0, ',', '.') ?> produk <?php if ($total > $perHal): ?><small>· halaman <?= $halaman ?></small><?php endif; ?></div>
        <select class="select" name="urut" aria-label="Urutkan" data-autosubmit>
          <?php foreach ($urutan as $kode => $u): ?><option value="<?= $kode ?>" <?= $urut === $kode ? 'selected' : '' ?>><?= e($u[0]) ?></option><?php endforeach; ?>
        </select>
      </div>

      <?php if ($q !== '' || $kat !== '' || $min || $max || $stokOk): ?>
      <div class="chips">
        <?php
        $chip = function (string $label, string $drop) use ($keep) { $k = $keep; unset($k[$drop]); return '<a class="chip" href="index.php' . ($k ? '?' . e(http_build_query($k)) : '') . '#produk">' . e($label) . ' ' . icon('x', 14) . '</a>'; };
        if ($q !== '') { echo $chip('“' . $q . '”', 'q'); }
        if ($kat !== '') { echo $chip($kat, 'kategori'); }
        if ($min) { echo $chip('Min ' . rupiah($min), 'min'); }
        if ($max) { echo $chip('Maks ' . rupiah($max), 'max'); }
        if ($stokOk) { echo $chip('Tersedia', 'stok'); }
        ?>
      </div>
      <?php endif; ?>

      <?php if ($produk): ?>
        <div class="grid">
          <?php foreach ($produk as $p) { echo product_card($p, $ROOT); } ?>
        </div>
        <?= paginate_links($halaman, max(1, (int)ceil($total / $perHal)), $keep) ?>
      <?php else: ?>
        <div class="empty">
          <div class="ic"><?= icon('search', 30) ?></div>
          <h3>Produk tidak ditemukan</h3>
          <p>Coba kata kunci lain atau hapus sebagian filter.</p>
          <a class="btn btn-primary" href="index.php#produk">Lihat semua produk</a>
        </div>
      <?php endif; ?>
    </div>
  </form>

  <?php if (!$filtering): ?>
  <div class="cta-band">
    <div><h2>Bingung memilih? Kami bantu.</h2><p>Tanya stok, spesifikasi, atau minta rekomendasi sesuai budgetmu.</p></div>
    <a class="btn btn-gold btn-lg" href="<?= e(wa_link('Halo admin, saya butuh bantuan memilih produk.')) ?>" target="_blank" rel="noopener"><?= icon_wa(20) ?> Chat WhatsApp</a>
  </div>
  <?php endif; ?>
</div></section>

<?php require ROOT_PATH . '/includes/store_footer.php'; ?>
