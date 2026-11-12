<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$ROOT = '../';
require_login();
$id = (int)($_SESSION['last_order_id'] ?? 0);
if (!$id) { redirect('riwayat.php'); }
$st = $pdo->prepare('SELECT * FROM pesanan WHERE id = ? AND pelanggan_id = ?');
$st->execute([$id, pelanggan_id()]);
$o = $st->fetch();
if (!$o) { redirect('riwayat.php'); }
$st = $pdo->prepare('SELECT dp.*, p.nama AS nama_produk, p.gambar FROM detail_pesanan dp JOIN produk p ON p.id = dp.produk_id WHERE dp.pesanan_id = ? ORDER BY dp.id');
$st->execute([$id]);
$detail = $st->fetchAll();
$kode = kode_pesanan($o['id'], $o['tanggal_pesanan']);
$transfer = ($o['metode_bayar'] ?? '') === 'Transfer Bank';
$waKonfirmasi = 'Halo admin, saya sudah membuat pesanan ' . $kode . ' atas nama ' . $o['nama_pelanggan'] . ' (total ' . rupiah($o['total_harga']) . '). Mohon dikonfirmasi.';

$page = ['title' => 'Pesanan berhasil', 'noindex' => true];
require ROOT_PATH . '/includes/store_header.php';
?>
<div class="wrap" style="max-width:820px">
  <div class="ok-hero">
    <div class="tick"><?= icon('check', 36) ?></div>
    <h1>Pesanan berhasil dibuat!</h1>
    <p class="muted">Terima kasih, <?= e($o['nama_pelanggan']) ?>. Simpan nomor pesananmu:</p>
    <div class="code"><?= e($kode) ?></div>
  </div>

  <div class="stack" style="padding-bottom:40px">
    <section class="card">
      <h2>Langkah selanjutnya</h2>
      <?php if ($transfer): ?>
        <p>Selesaikan pembayaran sebesar <b><?= rupiah($o['total_harga']) ?></b> dengan transfer, lalu unggah bukti pembayaran di halaman detail pesanan.</p>
        <?php if (!empty($APP['rekening'])): foreach ($APP['rekening'] as $r): ?>
          <div class="bank"><span><?= e($r['bank']) ?> · a.n. <?= e($r['nama']) ?></span><b><?= e($r['nomor']) ?></b></div>
        <?php endforeach; else: ?>
          <div class="alert alert-info">Nomor rekening akan dikirim admin lewat WhatsApp. Tekan tombol di bawah untuk meminta rekening &amp; konfirmasi pesanan.</div>
        <?php endif; ?>
      <?php else: ?>
        <p>Pesananmu akan dikonfirmasi oleh admin. Siapkan pembayaran <b><?= rupiah($o['total_harga']) ?></b> saat barang diterima.</p>
      <?php endif; ?>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px">
        <a class="btn btn-wa" href="<?= e(wa_link($waKonfirmasi)) ?>" target="_blank" rel="noopener"><?= icon_wa(18) ?> Konfirmasi via WhatsApp</a>
        <a class="btn btn-primary" href="detail_pesanan.php?id=<?= (int)$o['id'] ?>">Lihat detail pesanan</a>
        <a class="btn btn-ghost" href="index.php#produk">Lanjut belanja</a>
      </div>
    </section>

    <section class="card">
      <h2>Ringkasan</h2>
      <?php foreach ($detail as $d): $im = produk_img($d['gambar'], $ROOT, 'thumb'); ?>
        <div class="mini">
          <div class="th"><?php if ($im): ?><img src="<?= e($im) ?>" alt=""><?php endif; ?><em><?= (int)$d['jumlah'] ?></em></div>
          <div class="nm"><?= e($d['nama_produk']) ?></div><div class="pr"><?= rupiah($d['harga'] * $d['jumlah']) ?></div>
        </div>
      <?php endforeach; ?>
      <div style="border-top:1px solid var(--line);margin-top:10px;padding-top:10px">
        <div class="sum-row"><span class="muted">Subtotal</span><span><?= rupiah($o['total_harga'] - $o['ongkir']) ?></span></div>
        <div class="sum-row"><span class="muted">Ongkos kirim (<?= e($o['kurir'] ?: '-') ?>)</span><span><?= (int)$o['ongkir'] === 0 ? 'Gratis' : rupiah($o['ongkir']) ?></span></div>
        <div class="sum-row total"><span>Total</span><span><?= rupiah($o['total_harga']) ?></span></div>
      </div>
    </section>
  </div>
</div>
<?php require ROOT_PATH . '/includes/store_footer.php'; ?>
