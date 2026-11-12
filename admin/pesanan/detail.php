<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$ROOT = '../../'; $A = '../';
require_admin('../login.php');

$id = (int)qs('id');
$muat = function () use ($pdo, $id) { $s = $pdo->prepare('SELECT * FROM pesanan WHERE id = ?'); $s->execute([$id]); return $s->fetch(); };
$o = $muat();
if (!$o) { flash('error', 'Pesanan tidak ditemukan.'); redirect('index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = post('action');
    try {
        if ($aksi === 'status') {
            $baru = post('status');
            $kurir = mb_substr(post('kurir'), 0, 60); $resi = mb_substr(post('no_resi'), 0, 80);
            $pdo->beginTransaction();
            try {
                if ($baru === 'Dikirim') {
                    $pdo->prepare('UPDATE pesanan SET kurir = ?, no_resi = ? WHERE id = ?')->execute([$kurir !== '' ? $kurir : $o['kurir'], $resi !== '' ? $resi : null, $id]);
                }
                [$lama, $akhir] = ubah_status_pesanan($pdo, $id, $baru);
                $pdo->commit();
            } catch (Throwable $ex) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $ex; }
            $msg = 'Status pesanan diubah ke “' . status_info($akhir)[1] . '”.';
            if ($akhir === 'Dibatalkan' && $lama !== 'Dibatalkan') { $msg .= ' Stok produk dikembalikan.'; }
            if ($akhir === 'Selesai' && $lama !== 'Selesai' && $o['pelanggan_id']) { $msg .= ' Poin pelanggan ditambahkan.'; }
            flash('success', $msg);
        } elseif ($aksi === 'resi') {
            if (!in_array($o['status'], ['Diproses', 'Dikirim'], true)) { throw new AppError('Resi hanya bisa diisi untuk pesanan yang diproses atau dikirim.'); }
            $pdo->prepare('UPDATE pesanan SET kurir = ?, no_resi = ? WHERE id = ?')->execute([mb_substr(post('kurir'), 0, 60) ?: null, mb_substr(post('no_resi'), 0, 80) ?: null, $id]);
            flash('success', 'Informasi pengiriman disimpan.');
        }
    } catch (AppError $ex) {
        flash('error', $ex->getMessage());
    } catch (Throwable $ex) {
        error_log('admin detail pesanan: ' . $ex->getMessage());
        flash('error', 'Terjadi kesalahan saat menyimpan. Silakan coba lagi.');
    }
    redirect('detail.php?id=' . $id);
}

$st = $pdo->prepare('SELECT dp.*, p.nama AS nama_produk, p.gambar FROM detail_pesanan dp LEFT JOIN produk p ON p.id = dp.produk_id WHERE dp.pesanan_id = ? ORDER BY dp.id');
$st->execute([$id]);
$detail = $st->fetchAll();
$kode = kode_pesanan($o['id'], $o['tanggal_pesanan']);
$subtotal = 0; foreach ($detail as $d) { $subtotal += (int)$d['jumlah'] * (int)$d['harga']; }
$ongkir = (int)($o['ongkir'] ?? 0);
$pilihan = transisi_status()[$o['status']] ?? [];
$poinNanti = ($o['pelanggan_id'] && $o['status'] !== 'Selesai') ? hitungPoin((int)$o['total_harga'] - $ongkir) : 0;
$waCust = $o['no_hp'] ? 'https://wa.me/' . wa_number($o['no_hp']) . '?text=' . rawurlencode('Halo ' . $o['nama_pelanggan'] . ', terkait pesanan ' . $kode . ' di ' . $APP['nama'] . ': ') : '';
$kurirSaran = ['JNE', 'J&T Express', 'SiCepat', 'Pos Indonesia', 'AnterAja', 'GoSend', 'Grab Express'];
$label = ['Diproses' => 'Proses pesanan', 'Dikirim' => 'Tandai dikirim', 'Selesai' => 'Tandai selesai', 'Dibatalkan' => 'Batalkan pesanan', 'Menunggu' => 'Buka kembali pesanan'];

$adm = ['title' => 'Pesanan ' . $kode, 'nav' => 'pesanan'];
require __DIR__ . '/../includes/layout_top.php';
?>
<div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:18px">
  <a href="index.php" class="muted">&larr; Semua pesanan</a>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn btn-ghost btn-sm" href="invoice.php?id=<?= $id ?>" target="_blank" rel="noopener"><?= icon('print', 16) ?> Cetak invoice</a>
    <?php if ($waCust): ?><a class="btn btn-wa btn-sm" href="<?= e($waCust) ?>" target="_blank" rel="noopener"><?= icon_wa(16) ?> Hubungi pelanggan</a><?php endif; ?>
  </div>
</div>

<div class="fgrid">
  <div class="stack">
    <section class="card">
      <div class="head"><h2><?= e($kode) ?></h2><?= status_badge($o['status']) ?></div>
      <p class="muted" style="margin:-8px 0 16px;font-size:13.5px">Dibuat <?= tgl($o['tanggal_pesanan'], true) ?></p>
      <div class="scroll-x"><table class="tbl">
        <thead><tr><th>Produk</th><th>Harga</th><th>Qty</th><th class="right">Subtotal</th></tr></thead>
        <tbody>
        <?php foreach ($detail as $d): $im = produk_img($d['gambar'], $ROOT, 'thumb'); ?>
          <tr>
            <td><div class="rowi" style="padding:0;border:0"><span class="thumb"><?php if ($im): ?><img src="<?= e($im) ?>" alt=""><?php else: echo icon('package', 20); endif; ?></span><div class="grow"><b><?= e($d['nama_produk'] ?? 'Produk dihapus') ?></b></div></div></td>
            <td><?= rupiah($d['harga']) ?></td><td>×<?= (int)$d['jumlah'] ?></td><td class="right"><b><?= rupiah((int)$d['jumlah'] * (int)$d['harga']) ?></b></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <dl class="kv" style="margin-top:16px;grid-template-columns:1fr auto;border-top:1px solid var(--line);padding-top:16px">
        <dt>Subtotal produk</dt><dd class="right"><?= rupiah($subtotal) ?></dd>
        <dt>Ongkos kirim<?= $o['kurir'] ? ' (' . e($o['kurir']) . ')' : '' ?></dt><dd class="right"><?= $ongkir > 0 ? rupiah($ongkir) : 'Gratis' ?></dd>
        <dt><b style="color:var(--text)">Total</b></dt><dd class="right"><b style="font-size:18px"><?= rupiah($o['total_harga']) ?></b></dd>
      </dl>
      <?php if (!empty($o['catatan'])): ?><div class="alert alert-info" style="margin:16px 0 0"><b>Catatan pelanggan:</b><br><?= nl2br(e($o['catatan'])) ?></div><?php endif; ?>
    </section>

    <section class="card">
      <h2>Pelanggan &amp; pengiriman</h2>
      <dl class="kv admin">
        <dt>Nama</dt><dd><?= e($o['nama_pelanggan']) ?><?php if ($o['pelanggan_id']): ?> · <a href="../pelanggan/edit.php?id=<?= (int)$o['pelanggan_id'] ?>">lihat akun</a><?php else: ?> <span class="badge b-muted">Tamu</span><?php endif; ?></dd>
        <dt>Email</dt><dd><?= e($o['email'] ?: '—') ?></dd>
        <dt>No. HP</dt><dd><?= e($o['no_hp'] ?: '—') ?></dd>
        <dt>Alamat</dt><dd><?= nl2br(e($o['alamat'])) ?></dd>
      </dl>
    </section>
  </div>

  <div class="stack">
    <section class="card">
      <h2>Ubah status</h2>
      <?php if (!$pilihan): ?>
        <p class="muted" style="margin:0">Pesanan sudah selesai — status tidak dapat diubah lagi.</p>
      <?php else: ?>
        <div class="status-form">
        <?php foreach ($pilihan as $s):
            $batal = $s === 'Dibatalkan';
            $konfirm = $batal ? 'Batalkan pesanan ' . $kode . '? Stok produk akan dikembalikan.' : ($s === 'Selesai' ? 'Tandai pesanan selesai?' . ($poinNanti ? ' Pelanggan akan mendapat ' . $poinNanti . ' poin.' : '') : '');
        ?>
          <form method="post" <?= $konfirm ? 'data-confirm="' . e($konfirm) . '"' : '' ?> data-once>
            <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= e($s) ?>">
            <?php if ($s === 'Dikirim'): ?>
              <div class="field" style="margin-bottom:10px"><label for="kurir1">Kurir / layanan</label><input class="input" id="kurir1" name="kurir" list="kurirList" maxlength="60" value="<?= e($o['kurir']) ?>"></div>
              <div class="field" style="margin-bottom:10px"><label for="resi1">No. resi <span class="muted" style="font-weight:400">— opsional</span></label><input class="input" id="resi1" name="no_resi" maxlength="80" value="<?= e($o['no_resi']) ?>"></div>
            <?php endif; ?>
            <button class="btn <?= $batal ? 'btn-danger' : ($s === 'Menunggu' ? 'btn-ghost' : 'btn-primary') ?> btn-block"><?= e($label[$s] ?? $s) ?></button>
          </form>
        <?php endforeach; ?>
        </div>
        <?php if ($poinNanti > 0 && $o['status'] !== 'Dibatalkan'): ?><p class="muted" style="font-size:12.5px;margin:12px 0 0">Pelanggan mendapat <b><?= $poinNanti ?> poin</b> saat pesanan selesai.</p><?php endif; ?>
      <?php endif; ?>
    </section>

    <?php if (in_array($o['status'], ['Diproses', 'Dikirim'], true)): ?>
    <section class="card">
      <h2>Info pengiriman</h2>
      <form method="post" data-once>
        <?= csrf_field() ?><input type="hidden" name="action" value="resi">
        <div class="field"><label for="kurir2">Kurir / layanan</label><input class="input" id="kurir2" name="kurir" list="kurirList" maxlength="60" value="<?= e($o['kurir']) ?>"></div>
        <div class="field"><label for="resi2">No. resi</label><input class="input" id="resi2" name="no_resi" maxlength="80" value="<?= e($o['no_resi']) ?>"><div class="hint">Pelanggan melihat info ini di halaman pesanannya.</div></div>
        <button class="btn btn-ghost btn-block">Simpan info pengiriman</button>
      </form>
    </section>
    <?php elseif (!empty($o['no_resi'])): ?>
    <section class="card"><h2>Info pengiriman</h2><dl class="kv admin"><dt>Kurir</dt><dd><?= e($o['kurir'] ?: '—') ?></dd><dt>No. resi</dt><dd><?= e($o['no_resi']) ?></dd></dl></section>
    <?php endif; ?>

    <section class="card">
      <h2>Pembayaran</h2>
      <dl class="kv admin"><dt>Metode</dt><dd><?= e($o['metode_bayar'] ?: '—') ?></dd><dt>Bukti</dt><dd><?= !empty($o['bukti_bayar']) ? '<span class="badge b-ok">Sudah diunggah</span>' : '<span class="badge b-muted">Belum ada</span>' ?></dd></dl>
      <?php if (!empty($o['bukti_bayar'])): ?>
        <a href="../../public/bukti.php?id=<?= $id ?>" target="_blank" rel="noopener" style="display:block;margin-top:14px"><img src="../../public/bukti.php?id=<?= $id ?>" alt="Bukti pembayaran <?= e($kode) ?>" style="width:100%;max-height:340px;object-fit:contain;background:#f3f1eb;border:1px solid var(--line);border-radius:var(--r-sm)"></a>
      <?php endif; ?>
    </section>
  </div>
</div>
<datalist id="kurirList"><?php foreach ($kurirSaran as $k): ?><option value="<?= e($k) ?>"><?php endforeach; ?></datalist>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
