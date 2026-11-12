<?php
/**
 * Header toko. Sebelum di-include, halaman mengisi:
 *   $ROOT = '../';                         (path relatif ke root proyek)
 *   $page = ['title'=>..,'desc'=>..,'nav'=>..,'noindex'=>true,'canonical'=>..,'image'=>..,'head'=>..,'og_type'=>..];
 */
$ROOT = $ROOT ?? '../';
$page = $page ?? [];
$judul = !empty($page['title'])
    ? $page['title'] . ' — ' . $APP['nama']
    : $APP['nama'] . ' | Toko Online Gadget & Elektronik';
$desc = $page['desc'] ?? ('Belanja ' . mb_strtolower($APP['tagline']) . ' di ' . $APP['nama'] . '. Pesan mudah, pantau status pesanan langsung dari akunmu.');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$query = $_SERVER['QUERY_STRING'] ?? '';

if ($uri === '/' || $uri === '/index.php' ||
    $uri === '/toko-online/public/index.php') {
    $canonical = rtrim(origin(), '/') . '/';

    if (isset($_GET['kategori']) && $_GET['kategori'] !== '') {
        $canonical .= '?kategori=' . urlencode((string) $_GET['kategori']);
    }
} else {
    $canonical = $page['canonical'] ?? (
        rtrim(origin(), '/') . ($_SERVER['SCRIPT_NAME'] ?? '')
    );
}
try {
    $navKategori = $pdo->query("SELECT kategori, COUNT(*) AS n FROM produk WHERE aktif = 1 AND kategori IS NOT NULL AND kategori <> '' GROUP BY kategori ORDER BY kategori")->fetchAll();
} catch (Throwable $e) { $navKategori = []; }
$cartN = cart_count();
$katAktif = qs('kategori');
$qCari = qs('q', qs('search'));
$navOn = $page['nav'] ?? '';
$nama = $_SESSION['pelanggan_nama'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if (!empty($page['noindex'])): ?><meta name="robots" content="noindex, nofollow">
<?php else: ?><link rel="canonical" href="<?= e($canonical) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= e($APP['nama']) ?>">
<meta property="og:type" content="<?= e($page['og_type'] ?? 'website') ?>">
<meta property="og:title" content="<?= e($judul) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($judul) ?>">
<meta name="twitter:description" content="<?= e($desc) ?>">
<?php if (!empty($page['image'])): ?><meta property="og:image" content="<?= e($page['image']) ?>">
<?php endif; ?>
<?php if (!empty($APP['google_verification'])): ?><meta name="google-site-verification" content="<?= e($APP['google_verification']) ?>">
<?php endif; ?>
<meta name="theme-color" content="#1f2328">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap">
<link rel="stylesheet" href="assets/css/store.css?v=<?= (int)@filemtime(ROOT_PATH . '/public/assets/css/store.css') ?>">
<?= $page['head'] ?? '' ?>
</head>
<body>
<a class="skip" href="#main">Lewati ke konten utama</a>
<div class="toasts" id="toasts" aria-live="polite"><?= render_flash() ?></div>

<?php if (!empty($APP['promo_bar'])): ?>
<div class="promo"><div class="wrap"><?= e($APP['promo_bar']) ?> <a href="<?= e(wa_link()) ?>" target="_blank" rel="noopener">Chat WhatsApp</a></div></div>
<?php endif; ?>

<header class="site-header">
  <div class="wrap hdr">
    <a class="brand" href="index.php" aria-label="<?= e($APP['nama']) ?> — beranda">
      <span class="brand-mark">T</span><span class="brand-name">Tokkoku<em>Laku</em></span>
    </a>
    <form class="search" action="index.php" method="get" role="search">
      <?= icon('search', 18) ?>
      <input type="search" name="q" value="<?= e($qCari) ?>" placeholder="Cari laptop, smartwatch, smartphone…" aria-label="Cari produk" autocomplete="off">
      <button type="submit">Cari</button>
    </form>
    <div class="hdr-actions">
      <a class="hdr-link" href="cart.php" aria-label="Keranjang belanja, <?= $cartN ?> item">
        <?= icon('cart', 22) ?><span class="hide-sm">Keranjang</span>
        <?php if ($cartN > 0): ?><span class="count"><?= $cartN > 99 ? '99+' : $cartN ?></span><?php endif; ?>
      </a>
      <?php if (pelanggan_id()): ?>
        <details class="acct">
          <summary class="hdr-link"><span class="avatar"><?= e(mb_strtoupper(mb_substr($nama, 0, 1)) ?: 'U') ?></span><span class="hide-sm"><?= e(mb_strimwidth($nama, 0, 14, '…')) ?></span><?= icon('down', 16) ?></summary>
          <div class="acct-menu">
            <div class="who">Masuk sebagai<b><?= e($nama) ?></b></div>
            <a href="profil.php"><?= icon('user', 18) ?> Profil &amp; poin</a>
            <a href="riwayat.php"><?= icon('package', 18) ?> Pesanan saya</a>
            <a href="pesan.php"><?= icon('chat', 18) ?> Chat admin</a>
            <a href="logout.php"><?= icon('logout', 18) ?> Keluar</a>
          </div>
        </details>
      <?php else: ?>
        <a class="hdr-link" href="login.php"><?= icon('user', 22) ?><span class="hide-sm">Masuk</span></a>
        <a class="btn btn-gold btn-sm hide-sm" href="register.php">Daftar</a>
      <?php endif; ?>
    </div>
  </div>
  <nav class="catnav" aria-label="Kategori produk">
    <div class="wrap">
      <a href="index.php#produk" class="<?= ($navOn === 'home' && $katAktif === '') ? 'on' : '' ?>">Semua produk</a>
      <?php foreach ($navKategori as $k): ?>
        <a href="index.php?kategori=<?= urlencode($k['kategori']) ?>#produk" class="<?= $katAktif === $k['kategori'] ? 'on' : '' ?>"><?= e($k['kategori']) ?></a>
      <?php endforeach; ?>
      <span class="sp"></span>
      <a href="bantuan.php" class="<?= $navOn === 'bantuan' ? 'on' : '' ?>">Bantuan</a>
    </div>
  </nav>
</header>
<main id="main">
