<?php
/** Form produk bersama. Butuh: $form, $err, $lama (null = baru), $ROOT. */
$fe = function (string $k) use ($err) { return isset($err[$k]) ? '<div class="err">' . e($err[$k]) . '</div>' : ''; };
$cls = function (string $k) use ($err) { return 'field' . (isset($err[$k]) ? ' has-err' : ''); };
$im = $lama ? produk_img($form['gambar'] ?? $lama['gambar'], $ROOT, 'thumb') : '';
$daftarKat = $APP['kategori'];
if (!empty($form['kategori']) && !in_array($form['kategori'], $daftarKat, true)) { $daftarKat[] = $form['kategori']; }
?>
<?php if (!empty($err['_'])): ?><div class="alert alert-error"><?= e($err['_']) ?></div><?php endif; ?>
<?php if ($err && empty($err['_'])): ?><div class="alert alert-error">Periksa kembali isian yang ditandai merah.</div><?php endif; ?>
<form method="post" enctype="multipart/form-data" data-once>
  <?= csrf_field() ?>
  <div class="fgrid">
    <section class="card">
      <h2>Informasi produk</h2>
      <div class="<?= $cls('nama') ?>"><label for="nama">Nama produk</label><input class="input" id="nama" name="nama" maxlength="100" value="<?= e($form['nama']) ?>" required><?= $fe('nama') ?></div>
      <div class="grid2">
        <div class="<?= $cls('kategori') ?>"><label for="kategori">Kategori</label>
          <select class="select" id="kategori" name="kategori">
            <option value="">— Tanpa kategori —</option>
            <?php foreach ($daftarKat as $k): ?><option value="<?= e($k) ?>" <?= ($form['kategori'] ?? '') === $k ? 'selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?>
          </select><?= $fe('kategori') ?></div>
        <div class="<?= $cls('stok') ?>"><label for="stok">Stok</label><input class="input" id="stok" name="stok" inputmode="numeric" value="<?= e($form['stok']) ?>" required><?= $fe('stok') ?></div>
      </div>
      <div class="grid2">
        <div class="<?= $cls('harga') ?>"><label for="harga">Harga jual (Rp)</label><input class="input" id="harga" name="harga" inputmode="numeric" placeholder="mis. 1500000" value="<?= e($form['harga']) ?>" required><?= $fe('harga') ?></div>
        <div class="<?= $cls('harga_coret') ?>"><label for="harga_coret">Harga coret (Rp) <span class="muted" style="font-weight:400">— opsional</span></label><input class="input" id="harga_coret" name="harga_coret" inputmode="numeric" placeholder="Harga sebelum diskon" value="<?= e($form['harga_coret']) ?>"><?= $fe('harga_coret') ?>
          <div class="hint">Isi untuk menampilkan label diskon di toko.</div></div>
      </div>
      <div class="<?= $cls('deskripsi') ?>"><label for="deskripsi">Deskripsi</label><textarea class="textarea" id="deskripsi" name="deskripsi" rows="9" maxlength="5000" placeholder="Spesifikasi, kondisi, kelengkapan, garansi…"><?= e($form['deskripsi']) ?></textarea><?= $fe('deskripsi') ?>
        <div class="hint">Baris baru akan dipertahankan di halaman produk.</div></div>
    </section>

    <div class="stack">
      <section class="card">
        <h2>Foto produk</h2>
        <div class="preview" id="preview"><?php if ($im): ?><img src="<?= e($im) ?>" alt="Foto produk saat ini"><?php else: echo icon('package', 44); endif; ?></div>
        <?php if ($lama): $sf=$pdo->prepare('SELECT gambar FROM produk_foto WHERE produk_id=? ORDER BY urutan,id'); $sf->execute([(int)$lama['id']]); $fotoLain=$sf->fetchAll(PDO::FETCH_COLUMN); if ($fotoLain): ?><div style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0"><?php foreach($fotoLain as $gf): ?><img src="<?= e(produk_img($gf,$ROOT,'thumb')) ?>" alt="Foto tambahan" style="width:64px;height:64px;object-fit:cover;border-radius:8px"><?php endforeach; ?></div><?php endif; endif; ?>
        <div class="<?= $cls('gambar') ?>"><label for="gambar">Foto utama</label><input class="input" type="file" id="gambar" name="gambar" accept="image/jpeg,image/png,image/webp"><?= $fe('gambar') ?></div>
        <div style="margin-top:12px"><label for="foto_tambahan">Foto tambahan (bisa pilih banyak sekaligus)</label><input class="input" type="file" id="foto_tambahan" name="foto_tambahan[]" accept="image/jpeg,image/png,image/webp" multiple><div class="hint">JPG, PNG, WEBP · maksimal 4 MB per foto. Foto tambahan akan muncul sebagai galeri di halaman produk.</div><div id="foto-list" class="hint"></div></div>
        <?php if ($lama && $im): ?><label class="check"><input type="checkbox" name="hapus_gambar" value="1"> Hapus foto utama saat ini</label><?php endif; ?>
      </section>
      <section class="card">
        <h2>Tampilan di toko</h2>
        <label class="switch" style="border-top:0;padding-top:0"><input type="checkbox" name="aktif" value="1" <?= !empty($form['aktif']) ? 'checked' : '' ?>><span><b>Tampilkan di toko</b><small>Nonaktifkan untuk menyembunyikan produk tanpa menghapusnya.</small></span></label>
        <label class="switch"><input type="checkbox" name="unggulan" value="1" <?= !empty($form['unggulan']) ? 'checked' : '' ?>><span><b>Produk unggulan</b><small>Tampil di bagian “Produk unggulan” dan hero pada beranda (hanya jika stok tersedia).</small></span></label>
      </section>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-primary btn-lg" style="flex:1"><?= $lama ? 'Simpan perubahan' : 'Tambah produk' ?></button>
        <a class="btn btn-ghost btn-lg" href="index.php">Batal</a>
      </div>
    </div>
  </div>
</form>
<script>
(function(){
  var inp=document.getElementById('gambar'),box=document.getElementById('preview'),multi=document.getElementById('foto_tambahan'),list=document.getElementById('foto-list');
  if(multi&&list)multi.addEventListener('change',function(){list.textContent=multi.files.length?multi.files.length+' foto dipilih':'';});
  if(!inp||!box||!window.FileReader)return;
  inp.addEventListener('change',function(){
    var f=inp.files&&inp.files[0];if(!f||!/^image\//.test(f.type))return;
    var r=new FileReader();r.onload=function(e){box.innerHTML='';var i=new Image();i.src=e.target.result;i.alt='Pratinjau foto baru';box.appendChild(i);};r.readAsDataURL(f);
  });
})();
</script>
