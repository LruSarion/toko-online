<?php
/** Kerangka panel admin. Set sebelum include: $ROOT ('../' / '../../'), $A (basis admin: '' / '../'), $adm = ['title'=>, 'nav'=>]. */
$A = $A ?? ''; $ROOT = $ROOT ?? '../'; $adm = $adm ?? [];
$menungguN = (int)scalar($pdo, "SELECT COUNT(*) FROM pesanan WHERE status = 'Menunggu'");
$menu = [
    'dash'      => ['Dashboard', 'chart', $A . 'index.php', 0],
    'produk'    => ['Produk', 'package', $A . 'produk/index.php', 0],
    'pesanan'   => ['Pesanan', 'cart', $A . 'pesanan/index.php', $menungguN],
    'pelanggan' => ['Pelanggan', 'users', $A . 'pelanggan/index.php', 0],
    'chat'      => ['Chat', 'chat', $A . 'pesan.php', 0],
    'akun'      => ['Akun &amp; sistem', 'cog', $A . 'akun.php', 0],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($adm['title'] ?? 'Admin') . ' — Admin ' . $APP['nama']) ?></title>
<link rel="icon" href="<?= $ROOT ?>favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;1,600&display=swap">
<link rel="stylesheet" href="<?= $ROOT ?>assets/css/store.css?v=<?= (int)@filemtime(ROOT_PATH . '/public/assets/css/store.css') ?>">
<link rel="stylesheet" href="<?= $A ?>assets/admin.css?v=<?= (int)@filemtime(ROOT_PATH . '/admin/assets/admin.css') ?>">
</head>
<body class="adm">
<div class="toasts" aria-live="polite"><?= render_flash() ?></div>
<aside class="sb">
  <a class="brand" href="<?= $A ?>index.php"><span class="brand-mark">T</span><span class="brand-name">Tokkoku<em>Laku</em></span></a>
  <nav>
    <?php foreach ($menu as $k => $m): ?>
      <a class="nav <?= ($adm['nav'] ?? '') === $k ? 'on' : '' ?>" href="<?= $m[2] ?>"><?= icon($m[1], 19) ?> <?= $m[0] ?><?php if ($m[3] > 0): ?><span class="nb"><?= (int)$m[3] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="bottom">
    <a class="nav" href="/toko-online/public/index.php" target="_blank" rel="noopener"><?= icon('eye', 19) ?> Lihat toko</a>
    <a class="nav" href="<?= $A ?>logout.php"><?= icon('logout', 19) ?> Keluar</a>
  </div>
</aside>
<div style="min-width:0">
  <header class="tb">
    <h1><?= e($adm['title'] ?? 'Admin') ?></h1>
    <div class="who"><span class="avatar"><?= e(mb_strtoupper(mb_substr($_SESSION['admin_username'] ?? 'A', 0, 1))) ?></span><span class="hide-sm"><?= e($_SESSION['admin_username'] ?? '') ?></span></div>
  </header>
  <main class="ct">
