
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$ROOT = '../';
$id = (int)qs('id');

$st = $pdo->prepare(
    "SELECT produk.*, (
        SELECT COALESCE(SUM(dp.jumlah), 0)
        FROM detail_pesanan dp
        JOIN pesanan ps ON ps.id = dp.pesanan_id
        WHERE dp.produk_id = produk.id
        AND ps.status <> 'Dibatalkan'
    ) AS terjual
    FROM produk
    WHERE id = ? AND aktif = 1"
);
$st->execute([$id]);
$p = $st->fetch();

if (!$p) {
    http_response_code(404);
    $page = ['title' => 'Produk tidak ditemukan', 'noindex' => true];
    require ROOT_PATH . '/includes/store_header.php';
    ?>
    <div class="wrap" style="padding:48px 20px">
      <div class="empty">
        <div class="ic"><?= icon('search', 30) ?></div>
        <h2>Produk tidak ditemukan</h2>
        <p>Produk ini mungkin sudah tidak dijual atau tautannya salah.</p>
        <a class="btn btn-primary" href="index.php#produk">
          Lihat semua produk
        </a>
      </div>
    </div>
    <?php
    require ROOT_PATH . '/includes/store_footer.php';
    exit;
}

$stok = (int)$p['stok'];
$harga = (int)$p['harga'];
$coret = (int)($p['harga_coret'] ?? 0);
$disk = ($coret > $harga)
    ? (int)round(($coret - $harga) / $coret * 100)
    : 0;

$img = produk_img($p['gambar'], $ROOT, 'md');

$stFoto = $pdo->prepare(
    'SELECT gambar FROM produk_foto
     WHERE produk_id = ?
     ORDER BY urutan, id'
);
$stFoto->execute([$id]);

$fotoProduk = array_values(array_filter(array_map(
    fn($r) => produk_img($r['gambar'], $ROOT, 'md'),
    $stFoto->fetchAll()
)));

$semuaFoto = array_values(array_unique(array_filter(
    array_merge([$img], $fotoProduk)
)));

$stU = $pdo->prepare(
    'SELECT u.*, pl.nama AS nama_pelanggan
     FROM ulasan_produk u
     JOIN pelanggan pl ON pl.id = u.pelanggan_id
     WHERE u.produk_id = ?
     ORDER BY u.created_at DESC'
);
$stU->execute([$id]);
$ulasan = $stU->fetchAll();

$ratingRata = $ulasan
    ? array_sum(array_column($ulasan, 'rating')) / count($ulasan)
    : 0;

$deskripsi = trim((string)$p['deskripsi']);

if ($p['kategori'] !== null && $p['kategori'] !== '') {
    $st = $pdo->prepare(
        'SELECT * FROM produk
         WHERE aktif = 1 AND id <> ? AND kategori = ?
         ORDER BY (stok > 0) DESC, id DESC
         LIMIT 4'
    );
    $st->execute([$id, $p['kategori']]);
} else {
    $st = $pdo->prepare(
        'SELECT * FROM produk
         WHERE aktif = 1 AND id <> ?
         ORDER BY (stok > 0) DESC, id DESC
         LIMIT 4'
    );
    $st->execute([$id]);
}

$terkait = $st->fetchAll();

$canonical = rtrim(origin(), '/') . '/product.php?id=' . $id;

$ld = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $p['nama'],
    'description' => mb_substr(
        strip_tags($deskripsi !== '' ? $deskripsi : $p['nama']),
        0,
        300
    ),
    'offers' => [
        '@type' => 'Offer',
        'priceCurrency' => 'IDR',
        'price' => $harga,
        'url' => $canonical,
        'availability' => $stok > 0
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock',
    ],
];

if (!empty($p['kategori'])) {
    $ld['category'] = $p['kategori'];
}

if ($semuaFoto) {
    $ld['image'] = array_map(fn($x) => abs_url($x), $semuaFoto);
}

if ($ulasan) {
    $ld['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => round($ratingRata, 1),
        'reviewCount' => count($ulasan),
    ];
}

$page = [
    'nav' => 'home',
    'title' => $p['nama'],
    'og_type' => 'product',
    'canonical' => $canonical,
    'desc' => mb_substr(
        preg_replace(
            '/\s+/',
            ' ',
            strip_tags(
                $deskripsi !== ''
                    ? $deskripsi
                    : 'Beli ' . $p['nama'] . ' di ' . $APP['nama']
            )
        ),
        0,
        155
    ),
    'head' => '<script type="application/ld+json">'
        . json_encode(
            $ld,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
        )
        . '</script>',
];

if ($img) {
    $page['image'] = abs_url($img);
}

require ROOT_PATH . '/includes/store_header.php';

$waTanya = 'Halo admin, saya tertarik dengan '
    . $p['nama'] . ' (' . rupiah($harga)
    . '). Apakah masih tersedia?';
?>

<div class="wrap">

  <div class="crumbs">
    <a href="index.php">Beranda</a>
    <?= icon('right', 14) ?>

    <?php if ($p['kategori']): ?>
      <a href="index.php?kategori=<?= urlencode($p['kategori']) ?>#produk">
        <?= e($p['kategori']) ?>
      </a>
      <?= icon('right', 14) ?>
    <?php endif; ?>

    <span><?= e($p['nama']) ?></span>
  </div>

  <div class="pd">

    <div class="pd-img">

      <?php if ($img): ?>
        <img
          id="foto-utama"
          src="<?= e($img) ?>"
          alt="<?= e($p['nama']) ?>"
          fetchpriority="high"
        >
      <?php else: ?>
        <?= icon('package', 80) ?>
      <?php endif; ?>

      <?php if (count($semuaFoto) > 1): ?>
        <div
          class="galeri-mini"
          style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px"
        >
          <?php foreach ($semuaFoto as $idx => $foto): ?>
            <button
              type="button"
              class="galeri-btn"
              data-src="<?= e($foto) ?>"
              aria-label="Lihat foto <?= $idx + 1 ?>"
              aria-pressed="<?= $idx === 0 ? 'true' : 'false' ?>"
              style="padding:0;border:2px solid <?= $idx === 0 ? 'var(--primary)' : 'var(--line)' ?>;border-radius:8px;overflow:hidden;background:transparent;cursor:pointer"
            >
              <img
                src="<?= e($foto) ?>"
                alt="<?= e($p['nama']) ?> foto <?= $idx + 1 ?>"
                loading="lazy"
                style="width:68px;height:68px;object-fit:cover;display:block"
              >
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>

    <div>

      <?php if ($p['kategori']): ?>
        <a
          class="pcat"
          href="index.php?kategori=<?= urlencode($p['kategori']) ?>#produk"
        >
          <?= e($p['kategori']) ?>
        </a>
      <?php endif; ?>

      <h1><?= e($p['nama']) ?></h1>

      <div class="pd-price">
        <strong><?= rupiah($harga) ?></strong>

        <?php if ($disk): ?>
          <s><?= rupiah($coret) ?></s>
          <span class="badge b-bad">Hemat <?= $disk ?>%</span>
        <?php endif; ?>
      </div>

      <div class="pd-meta">
        <?php if ($stok < 1): ?>
          <span class="stock-out">Stok habis</span>
        <?php elseif ($stok <= (int)$APP['stok_rendah']): ?>
          <span class="stock-low">Tersisa <?= $stok ?> unit</span>
        <?php else: ?>
          <span class="stock-ok">Stok tersedia</span>
        <?php endif; ?>

        <?php if ((int)$p['terjual'] > 0): ?>
          <span>· Terjual <?= (int)$p['terjual'] ?></span>
        <?php endif; ?>
      </div>

      <?php if ($stok > 0): ?>

        <form method="post" action="cart.php" class="buy">
          <?= csrf_field() ?>

          <input type="hidden" name="produk_id" value="<?= $id ?>">
          <input
            type="hidden"
            name="back"
            value="<?= e(back_url()) ?>"
          >

          <div style="flex:0 0 100%">
            <div class="qty">
              <button
                type="button"
                data-step="-1"
                aria-label="Kurangi"
              >
                <?= icon('minus', 16) ?>
              </button>

              <input
                type="number"
                name="jumlah"
                value="1"
                min="1"
                max="<?= $stok ?>"
                aria-label="Jumlah"
              >

              <button
                type="button"
                data-step="1"
                aria-label="Tambah"
              >
                <?= icon('plus', 16) ?>
              </button>
            </div>
          </div>

          <button
            class="btn btn-primary btn-lg"
            name="action"
            value="tambah"
          >
            <?= icon('cart', 18) ?> Tambah ke keranjang
          </button>

          <button
            class="btn btn-gold btn-lg"
            name="action"
            value="beli"
          >
            Beli sekarang
          </button>
        </form>

      <?php else: ?>

        <div class="alert alert-info" style="margin:18px 0">
          Produk ini sedang kosong. Hubungi kami untuk info ketersediaan berikutnya.
        </div>

      <?php endif; ?>

      <a
        class="btn btn-ghost"
        href="<?= e(wa_link($waTanya)) ?>"
        target="_blank"
        rel="noopener"
      >
        <?= icon_wa(18) ?> Tanya produk ini
      </a>

      <div class="assure">

        <div>
          <?= icon('shield', 20) ?>
          <p style="margin:0">
            <b>Pesanan dipantau</b>
            <span>Status pesanan bisa dilihat kapan saja dari akunmu.</span>
          </p>
        </div>

        <div>
          <?= icon('truck', 20) ?>
          <p style="margin:0">
            <b>Pilih pengiriman</b>
            <span>Opsi &amp; biaya kirim ditampilkan jelas saat checkout.</span>
          </p>
        </div>

        <div>
          <?= icon('gift', 20) ?>
          <p style="margin:0">
            <b>Dapat poin</b>
            <span>
              Kamu mendapat <?= hitungPoin($harga) ?>
              poin per unit saat pesanan selesai.
            </span>
          </p>
        </div>

      </div>
    </div>
  </div>

  <section class="desc">
    <h2>Deskripsi produk</h2>

    <?php if ($deskripsi !== ''): ?>
      <p><?= nl2br(e($deskripsi)) ?></p>
    <?php else: ?>
      <p class="muted">Belum ada deskripsi untuk produk ini.</p>
    <?php endif; ?>

    <dl>
      <dt>Kategori</dt>
      <dd><?= e($p['kategori'] ?: '-') ?></dd>

      <dt>Ketersediaan</dt>
      <dd><?= $stok > 0 ? $stok . ' unit' : 'Habis' ?></dd>

      <dt>Ditambahkan</dt>
      <dd><?= tgl($p['create_at'] ?? '') ?></dd>
    </dl>
  </section>

  <section class="desc" id="ulasan-produk">
    <h2>Ulasan pelanggan</h2>

    <?php if ($ulasan): ?>
      <p>
        <strong>
          <?= number_format($ratingRata, 1, ',', '.') ?>/5
        </strong>
        · <?= count($ulasan) ?> ulasan
      </p>
    <?php else: ?>
      <p class="muted">Belum ada ulasan untuk produk ini.</p>
    <?php endif; ?>

    <?php foreach ($ulasan as $u): ?>
      <article style="padding:14px 0;border-bottom:1px solid var(--line)">

        <strong><?= e($u['nama_pelanggan']) ?></strong>

        <span
          aria-label="Rating <?= (int)$u['rating'] ?> dari 5"
          style="color:#d69e20"
        ><?= str_repeat('★', (int)$u['rating'])
            . str_repeat('☆', 5 - (int)$u['rating']) ?></span>

        <div class="muted" style="font-size:12px">
          <?= e(tgl($u['created_at'], true)) ?>
          · Pembelian terverifikasi
        </div>

        <p><?= nl2br(e($u['ulasan'])) ?></p>

        <?php if ($u['foto']): ?>
          <a
            href="<?= e($ROOT . 'uploads/ulasan/' . rawurlencode(basename($u['foto']))) ?>"
            target="_blank"
            rel="noopener"
          >
            <img
              src="<?= e($ROOT . 'uploads/ulasan/' . rawurlencode(basename($u['foto']))) ?>"
              alt="Foto ulasan pelanggan"
              loading="lazy"
              style="max-width:180px;max-height:180px;object-fit:cover;border-radius:10px"
            >
          </a>
        <?php endif; ?>

      </article>
    <?php endforeach; ?>

    <?php if (function_exists('pelanggan_id') && pelanggan_id()): ?>
      <p class="muted" style="margin-top:14px">
        Ulasan dan foto dapat ditambahkan dari detail pesanan setelah status pesanan selesai.
      </p>
    <?php else: ?>
      <p>
        <a href="login.php">Masuk</a>
        untuk menulis ulasan setelah pesanan selesai.
      </p>
    <?php endif; ?>
  </section>

  <?php if ($terkait): ?>
    <div class="sec-head">
      <div>
        <h2 style="font-size:24px">Produk terkait</h2>
      </div>
    </div>

    <div class="grid" style="margin-bottom:24px">
      <?php foreach ($terkait as $t) {
          echo product_card($t, $ROOT);
      } ?>
    </div>
  <?php endif; ?>

</div>

<script>
(function () {
    // Event delegation: klik thumbnail tetap bekerja
    // meskipun isi gambar utama berubah berkali-kali.
    document.addEventListener('click', function (event) {
        const tombol = event.target.closest('.galeri-btn');

        if (!tombol) return;

        const galeri = tombol.closest('.pd-img');

        if (!galeri) return;

        const fotoUtama = galeri.querySelector('#foto-utama');
        const sumberFoto = tombol.getAttribute('data-src');

        if (!fotoUtama || !sumberFoto) return;

        fotoUtama.src = sumberFoto;

        galeri.querySelectorAll('.galeri-btn').forEach(function (item) {
            item.style.borderColor = 'var(--line)';
            item.setAttribute('aria-pressed', 'false');
        });

        tombol.style.borderColor = 'var(--primary)';
        tombol.setAttribute('aria-pressed', 'true');
    });
})();
</script>

<?php require ROOT_PATH . '/includes/store_footer.php'; ?>
