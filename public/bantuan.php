<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$ROOT = '../';
$page = ['title' => 'Bantuan & FAQ', 'nav' => 'bantuan', 'desc' => 'Cara berbelanja, pembayaran, pengiriman, dan poin di ' . $APP['nama'] . '.'];
require ROOT_PATH . '/includes/store_header.php';
$kirim = []; foreach ($APP['ongkir'] as $o) { $kirim[] = $o['nama'] . ' (' . $o['estimasi'] . ', ' . ((int)$o['biaya'] === 0 ? 'gratis' : rupiah($o['biaya'])) . ')'; }
?>
<div class="wrap">
  <div class="page-h"><h1>Bantuan &amp; FAQ</h1><p>Jawaban untuk pertanyaan yang paling sering diajukan.</p></div>
  <div class="faq" style="padding:20px 0 24px">
    <details open><summary>Bagaimana cara berbelanja?</summary><div class="ans">Pilih produk, tekan <b>Tambah</b> atau <b>Beli sekarang</b>, buka keranjang, lalu lanjut ke <b>Checkout</b>. Isi data penerima, pilih pengiriman dan pembayaran, kemudian tekan <b>Buat pesanan</b>. Kamu perlu masuk ke akun saat checkout.</div></details>
    <details><summary>Apa saja metode pembayaran?</summary><div class="ans"><?= e(implode(', ', array_keys($APP['metode_bayar']))) ?>. Untuk transfer bank, unggah bukti pembayaran dari halaman detail pesanan agar admin dapat memverifikasinya.</div></details>
    <details><summary>Opsi pengiriman apa yang tersedia?</summary><div class="ans"><?= e(implode('; ', $kirim)) ?>.<?php if ((int)$APP['gratis_ongkir_min'] > 0): ?> Gratis ongkir untuk belanja minimal <?= rupiah($APP['gratis_ongkir_min']) ?>.<?php endif; ?></div></details>
    <details><summary>Bagaimana memantau status pesanan?</summary><div class="ans">Buka menu <a href="riwayat.php">Pesanan saya</a>. Status berubah dari Menunggu Konfirmasi, Diproses, Dikirim, hingga Selesai. Nomor resi akan tampil saat pesanan dikirim.</div></details>
    <details><summary>Bisakah pesanan dibatalkan?</summary><div class="ans">Pesanan yang masih berstatus Menunggu Konfirmasi dapat dibatalkan sendiri dari halaman detail pesanan. Setelah diproses, hubungi admin lewat WhatsApp.</div></details>
    <details><summary>Bagaimana sistem poin bekerja?</summary><div class="ans">Setiap belanja Rp10.000 (tanpa ongkir) kamu mendapat 1 poin. Poin masuk otomatis ketika pesanan berstatus Selesai dan bisa dilihat di <a href="profil.php">profil</a>.</div></details>
    <details><summary>Stok produk habis, apa yang harus dilakukan?</summary><div class="ans">Hubungi kami lewat WhatsApp untuk menanyakan jadwal ketersediaan kembali.</div></details>
  </div>
  <div class="cta-band" style="margin-top:8px">
    <div><h2>Masih butuh bantuan?</h2><p>Tim kami siap membantu melalui WhatsApp.</p></div>
    <a class="btn btn-gold btn-lg" href="<?= e(wa_link('Halo admin, saya butuh bantuan.')) ?>" target="_blank" rel="noopener"><?= icon_wa(20) ?> Hubungi kami</a>
  </div>
</div>
<?php require ROOT_PATH . '/includes/store_footer.php'; ?>
