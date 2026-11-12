<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$ROOT = '../';
require_login();
$filter = qs('status');
$valid = array_keys(transisi_status());
if (!in_array($filter, $valid, true)) { $filter = ''; }

$hitung = [];
$st = $pdo->prepare('SELECT status, COUNT(*) AS n FROM pesanan WHERE pelanggan_id = ? GROUP BY status');
$st->execute([pelanggan_id()]);
foreach ($st->fetchAll() as $r) { $hitung[$r['status']] = (int)$r['n']; }
$semua = array_sum($hitung);

$sql = 'SELECT p.*, (SELECT COALESCE(SUM(jumlah),0) FROM detail_pesanan WHERE pesanan_id = p.id) AS qty FROM pesanan p WHERE p.pelanggan_id = ?' . ($filter ? ' AND p.status = ?' : '') . ' ORDER BY p.id DESC LIMIT 100';
$st = $pdo->prepare($sql);
$st->execute($filter ? [pelanggan_id(), $filter] : [pelanggan_id()]);
$pesanan = $st->fetchAll();

$page = ['title' => 'Pesanan saya', 'noindex' => true];
require ROOT_PATH . '/includes/store_header.php';
?>
<div class="wrap" style="max-width:900px">
  <div class="page-h"><h1>Pesanan saya</h1><p>Pantau status dan riwayat belanjamu.</p></div>
  <div class="tabs" style="margin-top:16px">
    <a href="riwayat.php" class="<?= $filter === '' ? 'on' : '' ?>">Semua (<?= $semua ?>)</a>
    <?php foreach ($valid as $s): if (empty($hitung[$s])) { continue; } ?>
      <a href="riwayat.php?status=<?= urlencode($s) ?>" class="<?= $filter === $s ? 'on' : '' ?>"><?= e(status_info($s)[1]) ?> (<?= $hitung[$s] ?>)</a>
    <?php endforeach; ?>
  </div>

  <?php if ($pesanan): ?>
  <div class="olist" style="padding-bottom:40px">
    <?php foreach ($pesanan as $o): ?>
      <a class="ocard" href="detail_pesanan.php?id=<?= (int)$o['id'] ?>">
        <div><div class="oc"><?= e(kode_pesanan($o['id'], $o['tanggal_pesanan'])) ?></div><small><?= tgl($o['tanggal_pesanan'], true) ?> · <?= (int)$o['qty'] ?> barang</small></div>
        <div class="right"><?= status_badge($o['status']) ?></div>
        <div class="muted" style="font-size:13.5px"><?= e($o['metode_bayar'] ?: '-') ?> · <?= e($o['kurir'] ?: '-') ?></div>
        <div class="right"><b><?= rupiah($o['total_harga']) ?></b></div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty" style="margin-bottom:40px">
    <div class="ic"><?= icon('package', 30) ?></div>
    <h2><?= $filter ? 'Tidak ada pesanan dengan status ini' : 'Belum ada pesanan' ?></h2>
    <p>Pesanan yang kamu buat akan muncul di sini.</p>
    <a class="btn btn-primary" href="index.php#produk">Mulai belanja</a>
  </div>
  <?php endif; ?>
</div>
<?php require ROOT_PATH . '/includes/store_footer.php'; ?>
