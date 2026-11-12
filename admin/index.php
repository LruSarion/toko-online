<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$ROOT = '../'; $A = '';
require_admin();

$aktif = "('Diproses','Dikirim','Selesai')";   // status yang dihitung sebagai pendapatan
$awalBulan = date('Y-m-01 00:00:00'); $awalHari = date('Y-m-d 00:00:00');
$pendapatanBulan = (int)scalar($pdo, "SELECT COALESCE(SUM(total_harga),0) FROM pesanan WHERE status IN $aktif AND tanggal_pesanan >= ?", [$awalBulan]);
$pesananBulan    = (int)scalar($pdo, "SELECT COUNT(*) FROM pesanan WHERE status <> 'Dibatalkan' AND tanggal_pesanan >= ?", [$awalBulan]);
$pesananHariIni  = (int)scalar($pdo, "SELECT COUNT(*) FROM pesanan WHERE tanggal_pesanan >= ?", [$awalHari]);
$menunggu        = (int)scalar($pdo, "SELECT COUNT(*) FROM pesanan WHERE status = 'Menunggu'");
$perluKirim      = (int)scalar($pdo, "SELECT COUNT(*) FROM pesanan WHERE status = 'Diproses'");
$totalPelanggan  = (int)scalar($pdo, 'SELECT COUNT(*) FROM pelanggan');
$totalProduk     = (int)scalar($pdo, 'SELECT COUNT(*) FROM produk WHERE aktif = 1');
$batas = (int)$APP['stok_rendah'];

// Pendapatan 14 hari terakhir
$mulai = date('Y-m-d', strtotime('-13 days'));
$st = $pdo->prepare("SELECT DATE(tanggal_pesanan) AS d, SUM(total_harga) AS t FROM pesanan WHERE status IN $aktif AND tanggal_pesanan >= ? GROUP BY DATE(tanggal_pesanan)");
$st->execute([$mulai . ' 00:00:00']);
$perHari = [];
foreach ($st->fetchAll() as $r) { $perHari[$r['d']] = (int)$r['t']; }
$seri = [];
for ($i = 13; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i days")); $seri[] = [$d, $perHari[$d] ?? 0]; }
$maks = max(1, max(array_column($seri, 1)));
$total14 = array_sum(array_column($seri, 1));

$terlaris = $pdo->query("SELECT p.id, p.nama, p.gambar, SUM(dp.jumlah) AS qty, SUM(dp.jumlah * dp.harga) AS omzet
    FROM detail_pesanan dp JOIN pesanan ps ON ps.id = dp.pesanan_id JOIN produk p ON p.id = dp.produk_id
    WHERE ps.status IN $aktif GROUP BY p.id, p.nama, p.gambar ORDER BY qty DESC LIMIT 5")->fetchAll();
$st = $pdo->prepare('SELECT id, nama, stok, gambar FROM produk WHERE aktif = 1 AND stok <= ? ORDER BY stok ASC, id DESC LIMIT 6');
$st->execute([$batas]);
$menipis = $st->fetchAll();
$terbaru = $pdo->query('SELECT id, nama_pelanggan, total_harga, status, tanggal_pesanan FROM pesanan ORDER BY id DESC LIMIT 6')->fetchAll();

// Peringatan password lemah
$lemah = false;
$st = $pdo->prepare('SELECT * FROM admin WHERE id = ?'); $st->execute([admin_id()]); $me = $st->fetch();
if ($me && preg_match('/^\$2/', (string)$me['password'])) {
    foreach (['admin', 'admin123', 'password', '12345678', '123456', $me['username'], $me['username'] . '123'] as $c) { if (password_verify($c, $me['password'])) { $lemah = true; break; } }
}

$adm = ['title' => 'Dashboard', 'nav' => 'dash'];
require __DIR__ . '/includes/layout_top.php';
?>
<?php if ($lemah): ?>
<div class="alert alert-error"><b>Password akunmu mudah ditebak.</b> Segera ganti di <a href="akun.php"><b>Akun &amp; sistem</b></a> sebelum toko dipakai pelanggan.</div>
<?php endif; ?>

<div class="stats">
  <div class="stat"><span class="ic ok"><?= icon('chart', 22) ?></span><div><small>Pendapatan bulan ini</small><b><?= rupiah($pendapatanBulan) ?></b></div></div>
  <div class="stat"><span class="ic info"><?= icon('cart', 22) ?></span><div><small>Pesanan bulan ini</small><b><?= $pesananBulan ?></b><small>hari ini: <?= $pesananHariIni ?></small></div></div>
  <div class="stat"><span class="ic warn"><?= icon('alert', 22) ?></span><div><small>Perlu tindakan</small><b><?= $menunggu + $perluKirim ?></b><small><?= $menunggu ?> menunggu · <?= $perluKirim ?> perlu dikirim</small></div></div>
  <div class="stat"><span class="ic"><?= icon('users', 22) ?></span><div><small>Pelanggan · Produk aktif</small><b><?= $totalPelanggan ?> · <?= $totalProduk ?></b></div></div>
</div>

<div class="dgrid">
  <section class="card">
    <div class="head"><h2>Pendapatan 14 hari terakhir</h2><span class="muted" style="font-size:13px">Total <?= rupiah($total14) ?></span></div>
    <?php
    $W = 700; $H = 220; $pl = 8; $pb = 26; $n = count($seri); $bw = ($W - $pl * 2) / $n;
    ?>
    <svg class="chart" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Grafik pendapatan harian">
      <?php foreach ([0.25, 0.5, 0.75, 1] as $g): $y = ($H - $pb) - ($H - $pb - 10) * $g; ?>
        <line x1="<?= $pl ?>" x2="<?= $W - $pl ?>" y1="<?= round($y, 1) ?>" y2="<?= round($y, 1) ?>" stroke="#ece9e1" stroke-width="1"/>
      <?php endforeach; ?>
      <?php foreach ($seri as $i => $s):
        $h = $s[1] > 0 ? max(3, ($H - $pb - 10) * $s[1] / $maks) : 0;
        $x = $pl + $i * $bw + $bw * 0.18; $y = ($H - $pb) - $h; ?>
        <rect class="bar" x="<?= round($x, 1) ?>" y="<?= round($y, 1) ?>" width="<?= round($bw * 0.64, 1) ?>" height="<?= round($h, 1) ?>" rx="4"><title><?= e(tgl($s[0]) . ': ' . rupiah($s[1])) ?></title></rect>
        <?php if ($i % 2 === 0): ?><text x="<?= round($x + $bw * 0.32, 1) ?>" y="<?= $H - 8 ?>" text-anchor="middle"><?= date('j/n', strtotime($s[0])) ?></text><?php endif; ?>
      <?php endforeach; ?>
    </svg>
    <p class="muted" style="font-size:12.5px;margin:8px 0 0">Hanya pesanan berstatus Diproses, Dikirim, dan Selesai.</p>
  </section>

  <section class="card">
    <div class="head"><h2>Stok menipis</h2><a href="produk/index.php?stok=rendah" style="font-size:13px">Lihat semua</a></div>
    <?php if ($menipis): foreach ($menipis as $p): $im = produk_img($p['gambar'], $ROOT, 'thumb'); ?>
      <div class="rowi">
        <span class="thumb"><?php if ($im): ?><img src="<?= e($im) ?>" alt=""><?php else: echo icon('package', 20); endif; ?></span>
        <div class="grow"><b><?= e($p['nama']) ?></b><small><a href="produk/edit.php?id=<?= (int)$p['id'] ?>">Tambah stok</a></small></div>
        <span class="badge <?= (int)$p['stok'] === 0 ? 'b-bad' : 'b-warn' ?>"><?= (int)$p['stok'] === 0 ? 'Habis' : 'Sisa ' . (int)$p['stok'] ?></span>
      </div>
    <?php endforeach; else: ?><p class="muted" style="margin:0">Semua stok aman </p><?php endif; ?>
  </section>
</div>

<div class="dgrid">
  <section class="card">
    <div class="head"><h2>Pesanan terbaru</h2><a href="pesanan/index.php" style="font-size:13px">Semua pesanan</a></div>
    <?php if ($terbaru): ?>
    <div class="scroll-x"><table class="tbl stack-m">
      <thead><tr><th>Kode</th><th>Pelanggan</th><th>Total</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($terbaru as $o): ?>
        <tr><td><a href="pesanan/detail.php?id=<?= (int)$o['id'] ?>"><b><?= e(kode_pesanan($o['id'], $o['tanggal_pesanan'])) ?></b></a><br><small class="muted"><?= tgl($o['tanggal_pesanan'], true) ?></small></td>
            <td><?= e($o['nama_pelanggan']) ?></td><td><?= rupiah($o['total_harga']) ?></td><td><?= status_badge($o['status']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php else: ?><p class="muted" style="margin:0">Belum ada pesanan.</p><?php endif; ?>
  </section>

  <section class="card">
    <div class="head"><h2>Produk terlaris</h2></div>
    <?php if ($terlaris): foreach ($terlaris as $p): $im = produk_img($p['gambar'], $ROOT, 'thumb'); ?>
      <div class="rowi">
        <span class="thumb"><?php if ($im): ?><img src="<?= e($im) ?>" alt=""><?php else: echo icon('package', 20); endif; ?></span>
        <div class="grow"><b><?= e($p['nama']) ?></b><small><?= rupiah($p['omzet']) ?></small></div>
        <span class="badge b-ok"><?= (int)$p['qty'] ?> terjual</span>
      </div>
    <?php endforeach; else: ?><p class="muted" style="margin:0">Belum ada penjualan.</p><?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
