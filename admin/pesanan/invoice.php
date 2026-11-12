<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin('../login.php');
$id = (int)qs('id');
$st = $pdo->prepare('SELECT * FROM pesanan WHERE id = ?');
$st->execute([$id]);
$o = $st->fetch();
if (!$o) { abort_page(404, 'Tidak ditemukan', 'Pesanan tidak ditemukan.'); }
$st = $pdo->prepare('SELECT dp.*, p.nama AS nama_produk FROM detail_pesanan dp LEFT JOIN produk p ON p.id = dp.produk_id WHERE dp.pesanan_id = ? ORDER BY dp.id');
$st->execute([$id]);
$detail = $st->fetchAll();
$kode = kode_pesanan($o['id'], $o['tanggal_pesanan']);
$subtotal = 0; foreach ($detail as $d) { $subtotal += (int)$d['jumlah'] * (int)$d['harga']; }
$ongkir = (int)($o['ongkir'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Invoice <?= e($kode) ?></title>
<style>
  *{box-sizing:border-box}
  body{font:14px/1.55 Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#1b1e22;background:#eceae4;margin:0;padding:24px}
  .sheet{max-width:780px;margin:0 auto;background:#fff;padding:44px;border-radius:12px;box-shadow:0 6px 30px rgba(0,0,0,.08)}
  .top{display:flex;justify-content:space-between;gap:24px;border-bottom:2px solid #1b1e22;padding-bottom:20px;margin-bottom:24px}
  h1{font:600 26px/1.2 "Playfair Display",Georgia,serif;margin:0 0 4px}
  .meta{text-align:right}.meta b{font-size:18px;display:block}
  .cols{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px}
  .cols h3{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#7a7f87;margin:0 0 6px}
  table{width:100%;border-collapse:collapse}
  th{text-align:left;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#7a7f87;border-bottom:1px solid #d9d6cc;padding:8px 6px}
  td{padding:11px 6px;border-bottom:1px solid #eeece5}
  .r{text-align:right}
  .tot{margin:16px 0 0 auto;width:min(320px,100%)}
  .tot div{display:flex;justify-content:space-between;padding:5px 0}
  .tot .g{border-top:2px solid #1b1e22;margin-top:6px;padding-top:10px;font-size:17px;font-weight:700}
  .foot{margin-top:36px;padding-top:16px;border-top:1px solid #eeece5;color:#7a7f87;font-size:12.5px;text-align:center}
  .bar{max-width:780px;margin:0 auto 14px;display:flex;justify-content:space-between;gap:10px}
  .bar a,.bar button{font:600 14px Inter,system-ui,sans-serif;padding:10px 18px;border-radius:999px;border:1px solid #cfcbbf;background:#fff;color:#1b1e22;text-decoration:none;cursor:pointer}
  .bar button{background:#1b1e22;color:#fff;border-color:#1b1e22}
  @media (max-width:600px){.sheet{padding:22px}.cols{grid-template-columns:1fr}.top{flex-direction:column}.meta{text-align:left}}
  @media print{body{background:#fff;padding:0}.sheet{box-shadow:none;border-radius:0;padding:0;max-width:none}.bar{display:none}}
</style>
</head>
<body>
<div class="bar"><a href="detail.php?id=<?= $id ?>">&larr; Kembali</a><button type="button" onclick="window.print()">Cetak / simpan PDF</button></div>
<div class="sheet">
  <div class="top">
    <div><h1><?= e($APP['nama']) ?></h1><div style="color:#7a7f87"><?= e($APP['tagline']) ?><?php if (!empty($APP['alamat'])): ?><br><?= e($APP['alamat']) ?><?php endif; ?><br>WhatsApp <?= e('+' . $APP['whatsapp']) ?></div></div>
    <div class="meta"><span style="color:#7a7f87;font-size:11px;letter-spacing:.08em;text-transform:uppercase">Invoice</span><b><?= e($kode) ?></b><?= tgl($o['tanggal_pesanan'], true) ?><br>Status: <?= e(status_info($o['status'])[1]) ?></div>
  </div>
  <div class="cols">
    <div><h3>Ditagihkan kepada</h3><b><?= e($o['nama_pelanggan']) ?></b><br><?= e($o['no_hp']) ?><?= $o['email'] ? '<br>' . e($o['email']) : '' ?></div>
    <div><h3>Dikirim ke</h3><?= nl2br(e($o['alamat'])) ?><?php if ($o['kurir']): ?><br><span style="color:#7a7f87">via <?= e($o['kurir']) ?><?= $o['no_resi'] ? ' · resi ' . e($o['no_resi']) : '' ?></span><?php endif; ?></div>
  </div>
  <table>
    <thead><tr><th>Produk</th><th class="r">Harga</th><th class="r">Qty</th><th class="r">Subtotal</th></tr></thead>
    <tbody>
    <?php foreach ($detail as $d): ?>
      <tr><td><?= e($d['nama_produk'] ?? 'Produk dihapus') ?></td><td class="r"><?= rupiah($d['harga']) ?></td><td class="r"><?= (int)$d['jumlah'] ?></td><td class="r"><?= rupiah((int)$d['jumlah'] * (int)$d['harga']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="tot">
    <div><span>Subtotal</span><span><?= rupiah($subtotal) ?></span></div>
    <div><span>Ongkos kirim</span><span><?= $ongkir > 0 ? rupiah($ongkir) : 'Gratis' ?></span></div>
    <div class="g"><span>Total</span><span><?= rupiah($o['total_harga']) ?></span></div>
  </div>
  <p style="margin:22px 0 0"><b>Pembayaran:</b> <?= e($o['metode_bayar'] ?: '—') ?></p>
  <div class="foot">Terima kasih telah berbelanja di <?= e($APP['nama']) ?>.</div>
</div>
</body>
</html>
