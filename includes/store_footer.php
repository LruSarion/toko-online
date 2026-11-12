</main>
<footer class="site-footer">
  <div class="wrap foot">
    <div>
      <img src="assets/img/logo.webp" alt="<?= e($APP['nama']) ?>" width="140" height="99">
      <p><?= e($APP['tagline']) ?>. Belanja praktis, stok jelas, status pesanan bisa dipantau kapan saja.</p>
    </div>
    <div>
      <h4>Belanja</h4>
      <a href="index.php#produk">Semua produk</a>
      <?php foreach (array_slice($navKategori ?? [], 0, 5) as $k): ?>
        <a href="index.php?kategori=<?= urlencode($k['kategori']) ?>#produk"><?= e($k['kategori']) ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>Bantuan &amp; akun</h4>
      <a href="bantuan.php">Cara belanja &amp; FAQ</a>
      <a href="riwayat.php">Pesanan saya</a>
      <a href="profil.php">Profil &amp; poin</a>
      <a href="chat.php">Chat admin</a>
    </div>
    <div>
      <h4>Hubungi kami</h4>
      <p><a href="<?= e(wa_link()) ?>" target="_blank" rel="noopener">WhatsApp: +<?= e($APP['whatsapp']) ?> Founder & Administrator (M***h*el)</a></p>
      <p><a href="<?= e(wa_link()) ?>" target="_blank" rel="noopener">WhatsApp: +<?= e($APP['whatsapp']) ?></a></p>
      <?php if (!empty($APP['email'])): ?><p><a href="mailto:<?= e($APP['email']) ?>"><?= e($APP['email']) ?></a></p><?php endif; ?>
      <?php if (!empty($APP['alamat'])): ?><p><?= e($APP['alamat']) ?></p><?php endif; ?>
    </div>
  </div>
  <div class="wrap"><div class="copy"><span>&copy; <?= date('Y') ?> <?= e($APP['nama']) ?>. Seluruh hak cipta dilindungi.</span><span>Harga dalam Rupiah (IDR)</span></div></div>
</footer>
<a class="wa-float" href="<?= e(wa_link('Halo admin, saya ingin bertanya mengenai produk di ' . $APP['nama'] . '.')) ?>" target="_blank" rel="noopener" aria-label="Chat via WhatsApp">
  <?= icon_wa(24) ?><span>WhatsApp</span>
</a>
<script src="assets/js/store.js?v=<?= (int)@filemtime(ROOT_PATH . '/public/assets/js/store.js') ?>"></script>
</body>
</html>
