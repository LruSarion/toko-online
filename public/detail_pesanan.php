
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$ROOT = '../';
require_login();

$id = (int)qs('id');
$st = $pdo->prepare('SELECT * FROM pesanan WHERE id = ? AND pelanggan_id = ?');
$st->execute([$id, pelanggan_id()]);
$o = $st->fetch();

if (!$o) {
    flash('error', 'Pesanan tidak ditemukan atau bukan milik akunmu.');
    redirect('riwayat.php');
}

// ---------- Aksi: unggah bukti / ulasan / batalkan ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = post('action');

    try {
        if ($aksi === 'bukti') {
            if ($o['status'] !== 'Menunggu') {
                throw new AppError('Bukti pembayaran hanya bisa diunggah untuk pesanan yang masih menunggu.');
            }

            if (!isset($_FILES['bukti'])) {
                throw new AppError('Pilih file bukti pembayaran terlebih dahulu.');
            }

            [$ok, $hasil] = upload_gambar(
                $_FILES['bukti'],
                ROOT_PATH . '/uploads/bukti',
                'bukti_' . $id . '_'
            );

            if (!$ok) {
                throw new AppError($hasil);
            }

            if (!empty($o['bukti_bayar'])) {
                @unlink(ROOT_PATH . '/uploads/bukti/' . basename($o['bukti_bayar']));
            }

            $pdo->prepare('UPDATE pesanan SET bukti_bayar = ? WHERE id = ?')
                ->execute([$hasil, $id]);

            flash('success', 'Bukti pembayaran berhasil diunggah. Admin akan segera memeriksanya.');

        } elseif ($aksi === 'ulasan') {
            if ($o['status'] !== 'Selesai') {
                throw new AppError('Ulasan hanya bisa diberikan setelah pesanan selesai/diterima.');
            }

            $pid = (int)post('produk_id');
            $rating = (int)post('rating');
            $isi = post('ulasan');

            $cek = $pdo->prepare(
                'SELECT COUNT(*) FROM detail_pesanan WHERE pesanan_id=? AND produk_id=?'
            );
            $cek->execute([$id, $pid]);

            if (!(int)$cek->fetchColumn()) {
                throw new AppError('Produk ini tidak ditemukan di pesanan tersebut.');
            }

            if ($rating < 1 || $rating > 5) {
                throw new AppError('Pilih rating 1 sampai 5 bintang.');
            }

            if (mb_strlen($isi) < 5 || mb_strlen($isi) > 2000) {
                throw new AppError('Ulasan harus 5–2000 karakter.');
            }

            if ((int)scalar(
                $pdo,
                'SELECT COUNT(*) FROM ulasan_produk WHERE pesanan_id=? AND produk_id=?',
                [$id, $pid]
            )) {
                throw new AppError('Kamu sudah mengulas produk ini.');
            }

            $foto = null;

            if (
                isset($_FILES['foto_ulasan']) &&
                (int)($_FILES['foto_ulasan']['error'] ?? UPLOAD_ERR_NO_FILE)
                    !== UPLOAD_ERR_NO_FILE
            ) {
                [$ok, $hasil] = upload_gambar(
                    $_FILES['foto_ulasan'],
                    ROOT_PATH . '/uploads/ulasan',
                    'review_' . $id . '_'
                );

                if (!$ok) {
                    throw new AppError($hasil);
                }

                $foto = $hasil;
            }

            try {
                $pdo->prepare(
                    'INSERT INTO ulasan_produk
                    (produk_id,pesanan_id,pelanggan_id,rating,ulasan,foto)
                    VALUES (?,?,?,?,?,?)'
                )->execute([
                    $pid,
                    $id,
                    pelanggan_id(),
                    $rating,
                    $isi,
                    $foto
                ]);
            } catch (Throwable $ex) {
                if ($foto) {
                    @unlink(ROOT_PATH . '/uploads/ulasan/' . $foto);
                }

                throw new AppError(
                    'Ulasan gagal disimpan. Mungkin produk ini sudah pernah kamu ulas.'
                );
            }

            flash('success', 'Terima kasih! Ulasan produk berhasil dikirim.');

        } elseif ($aksi === 'batal') {
            ubah_status_pesanan($pdo, $id, 'Dibatalkan', pelanggan_id());
            flash('info', 'Pesanan telah dibatalkan.');
        }

    } catch (AppError $e) {
        flash('error', $e->getMessage());

    } catch (Throwable $e) {
        error_log('detail_pesanan: ' . $e->getMessage());
        flash('error', 'Terjadi kesalahan. Silakan coba lagi.');
    }

    redirect('detail_pesanan.php?id=' . $id);
}

$st = $pdo->prepare(
    'SELECT dp.*, p.nama AS nama_produk, p.gambar
     FROM detail_pesanan dp
     JOIN produk p ON p.id = dp.produk_id
     WHERE dp.pesanan_id = ?
     ORDER BY dp.id'
);
$st->execute([$id]);
$detail = $st->fetchAll();

$kode = kode_pesanan($o['id'], $o['tanggal_pesanan']);
$transfer = ($o['metode_bayar'] ?? '') === 'Transfer Bank';

$alur = [
    'Menunggu' => 'Pesanan dibuat',
    'Diproses' => 'Diproses',
    'Dikirim' => 'Dikirim',
    'Selesai' => 'Selesai'
];

$urutan = array_keys($alur);
$posisi = array_search($o['status'], $urutan, true);
$waTanya = 'Halo admin, saya ingin menanyakan pesanan ' . $kode . '.';

$page = ['title' => 'Pesanan ' . $kode, 'noindex' => true];
require ROOT_PATH . '/includes/store_header.php';
?>

<div class="wrap" style="max-width:960px">
    <div class="crumbs">
        <a href="riwayat.php">Pesanan saya</a>
        <?= icon('right', 14) ?>
        <span><?= e($kode) ?></span>
    </div>

    <div class="page-h" style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:center">
        <div>
            <h1><?= e($kode) ?></h1>
            <p>Dibuat <?= tgl($o['tanggal_pesanan'], true) ?></p>
        </div>
        <div><?= status_badge($o['status']) ?></div>
    </div>

    <div class="stack" style="padding:16px 0 40px">

        <?php if ($o['status'] === 'Dibatalkan'): ?>
            <div class="alert alert-error" style="margin:0">
                Pesanan ini dibatalkan. Stok produk sudah dikembalikan.
            </div>
        <?php else: ?>
            <section class="card">
                <div class="timeline">
                    <?php foreach ($alur as $s => $label):
                        $i = array_search($s, $urutan, true);
                        $cls = $i < $posisi ? 'done' : ($i === $posisi ? 'now' : '');

                        if ($o['status'] === 'Selesai' && $i === $posisi) {
                            $cls = 'done';
                        }
                    ?>
                        <div class="tl <?= $cls ?>">
                            <i><?= $cls === 'done' ? icon('check', 16) : $i + 1 ?></i>
                            <?= e($label) ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($o['no_resi'])): ?>
                    <p style="margin:14px 0 0;text-align:center">
                        Kurir <b><?= e($o['kurir']) ?></b>
                        · No. resi <b><?= e($o['no_resi']) ?></b>
                    </p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($o['status'] === 'Menunggu' && $transfer): ?>
            <section class="card">
                <h2>Pembayaran</h2>
                <p>Total yang harus dibayar: <b><?= rupiah($o['total_harga']) ?></b></p>

                <?php foreach ($APP['rekening'] as $r): ?>
                    <div class="bank">
                        <span><?= e($r['bank']) ?> · a.n. <?= e($r['nama']) ?></span>
                        <b><?= e($r['nomor']) ?></b>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($APP['rekening'])): ?>
                    <div class="alert alert-info">
                        Minta nomor rekening ke admin lewat WhatsApp, lalu unggah bukti transfer di bawah.
                    </div>
                <?php endif; ?>

                <?php if (!empty($o['bukti_bayar'])): ?>
                    <p class="plus">
                        ✓ Bukti pembayaran sudah diunggah.
                        <a href="bukti.php?id=<?= $id ?>" target="_blank" rel="noopener">Lihat</a>
                    </p>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data"
                      style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bukti">

                    <input class="input" style="flex:1;min-width:200px"
                           type="file" name="bukti"
                           accept="image/jpeg,image/png,image/webp" required>

                    <button class="btn btn-primary">
                        <?= empty($o['bukti_bayar']) ? 'Unggah bukti' : 'Ganti bukti' ?>
                    </button>
                </form>

                <div class="muted" style="font-size:12.5px;margin-top:8px">
                    Format JPG, PNG, atau WEBP, maksimal 4 MB.
                </div>
            </section>
        <?php endif; ?>

        <section class="card">
            <h2>Produk dipesan</h2>

            <?php foreach ($detail as $d):
                $im = produk_img($d['gambar'], $ROOT, 'thumb');
            ?>
                <div class="mini">
                    <div class="th">
                        <?php if ($im): ?>
                            <img src="<?= e($im) ?>" alt="">
                        <?php endif; ?>
                        <em><?= (int)$d['jumlah'] ?></em>
                    </div>

                    <div class="nm">
                        <a href="product.php?id=<?= (int)$d['produk_id'] ?>"
                           style="color:inherit">
                            <?= e($d['nama_produk']) ?>
                        </a>
                        <br>
                        <small class="muted">
                            <?= rupiah($d['harga']) ?> × <?= (int)$d['jumlah'] ?>
                        </small>
                    </div>

                    <div class="pr">
                        <?= rupiah($d['harga'] * $d['jumlah']) ?>
                    </div>
                </div>

                <?php if ($o['status'] === 'Selesai'):
                    $sudah = $pdo->prepare(
                        'SELECT id FROM ulasan_produk WHERE pesanan_id=? AND produk_id=?'
                    );
                    $sudah->execute([$id, (int)$d['produk_id']]);
                    $reviewAda = $sudah->fetchColumn();
                ?>

                    <?php if ($reviewAda): ?>
                        <p class="muted" style="font-size:13px;margin:0 0 14px">
                            ✓ Kamu sudah mengulas produk ini.
                        </p>
                    <?php else: ?>

                        <form method="post" enctype="multipart/form-data"
                              class="card form-ulasan"
                              style="margin:0 0 16px;padding:16px;background:var(--surface, #fafafa)">

                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="ulasan">
                            <input type="hidden" name="produk_id"
                                   value="<?= (int)$d['produk_id'] ?>">

                            <h3 style="margin:0 0 10px;font-size:16px">
                                Beri ulasan: <?= e($d['nama_produk']) ?>
                            </h3>

                            <label for="rating-<?= (int)$d['produk_id'] ?>">Rating</label>
                            <select class="select"
                                    id="rating-<?= (int)$d['produk_id'] ?>"
                                    name="rating" required>
                                <option value="">Pilih bintang</option>
                                <?php for ($r = 5; $r >= 1; $r--): ?>
                                    <option value="<?= $r ?>">
                                        <?= $r ?> bintang <?= str_repeat('★', $r) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>

                            <label for="ulasan-<?= (int)$d['produk_id'] ?>"
                                   style="display:block;margin-top:10px">
                                Ulasan
                            </label>

                            <textarea class="textarea"
                                      id="ulasan-<?= (int)$d['produk_id'] ?>"
                                      name="ulasan" rows="3"
                                      minlength="5" maxlength="2000"
                                      placeholder="Ceritakan pengalamanmu setelah menerima barang…"
                                      required></textarea>

                            <label style="display:block;margin-top:10px">
                                Foto produk (opsional)
                            </label>

                            <input
                                class="input input-foto-ulasan"
                                type="file"
                                id="foto-ulasan-<?= (int)$d['produk_id'] ?>"
                                name="foto_ulasan"
                                accept="image/jpeg,image/png,image/webp"
                                style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden"
                            >

                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
                                <button type="button"
                                        class="btn btn-ghost btn-kamera"
                                        onclick="pilihFotoUlasan(this, true)">
                                    📸 Buka Kamera
                                </button>

                                <button type="button"
                                        class="btn btn-ghost"
                                        onclick="pilihFotoUlasan(this, false)">
                                    🖼️ Pilih dari Galeri
                                </button>
                            </div>

                            <small class="muted nama-foto-ulasan"
                                   style="display:block;margin-top:8px">
                                JPG, PNG, WEBP · maksimal 4 MB.
                            </small>

                            <button type="submit" class="btn btn-primary"
                                    style="margin-top:12px">
                                Kirim ulasan
                            </button>
                        </form>

                    <?php endif; ?>
                <?php endif; ?>

            <?php endforeach; ?>

            <div style="border-top:1px solid var(--line);margin-top:10px;padding-top:10px;max-width:380px;margin-left:auto">
                <div class="sum-row">
                    <span class="muted">Subtotal</span>
                    <span><?= rupiah($o['total_harga'] - $o['ongkir']) ?></span>
                </div>

                <div class="sum-row">
                    <span class="muted">Ongkos kirim</span>
                    <span><?= (int)$o['ongkir'] === 0 ? 'Gratis' : rupiah($o['ongkir']) ?></span>
                </div>

                <div class="sum-row total">
                    <span>Total</span>
                    <span><?= rupiah($o['total_harga']) ?></span>
                </div>
            </div>
        </section>

        <section class="card">
            <h2>Informasi pengiriman &amp; pembayaran</h2>

            <dl class="kv">
                <dt>Penerima</dt>
                <dd><?= e($o['nama_pelanggan']) ?></dd>

                <dt>No. HP</dt>
                <dd><?= e($o['no_hp']) ?></dd>

                <dt>Email</dt>
                <dd><?= e($o['email'] ?: '-') ?></dd>

                <dt>Alamat</dt>
                <dd><?= nl2br(e($o['alamat'])) ?></dd>

                <dt>Pengiriman</dt>
                <dd><?= e($o['kurir'] ?: '-') ?></dd>

                <dt>Pembayaran</dt>
                <dd><?= e($o['metode_bayar'] ?: '-') ?></dd>

                <?php if (!empty($o['catatan'])): ?>
                    <dt>Catatan</dt>
                    <dd><?= nl2br(e($o['catatan'])) ?></dd>
                <?php endif; ?>
            </dl>
        </section>

        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="btn btn-wa"
               href="<?= e(wa_link($waTanya)) ?>"
               target="_blank" rel="noopener">
                <?= icon_wa(18) ?> Tanya admin
            </a>

            <?php if ($o['status'] === 'Menunggu'): ?>
                <form method="post"
                      data-confirm="Batalkan pesanan ini? Tindakan tidak dapat diurungkan.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="batal">
                    <button class="btn btn-danger">Batalkan pesanan</button>
                </form>
            <?php endif; ?>

            <a class="btn btn-ghost" href="riwayat.php">Kembali</a>
        </div>
    </div>
</div>

<script>
function pilihFotoUlasan(tombol, gunakanKamera) {
    const form = tombol.closest('form');
    if (!form) return;

    const input = form.querySelector('.input-foto-ulasan');
    const label = form.querySelector('.nama-foto-ulasan');

    if (!input) return;

    // Atur pilihan kamera atau galeri sebelum membuka pemilih file.
    if (gunakanKamera) {
        input.setAttribute('capture', 'environment');
    } else {
        input.removeAttribute('capture');
    }

    // Tampilkan nama foto setelah pelanggan memilih atau mengambil gambar.
    input.onchange = function () {
        if (input.files && input.files.length > 0) {
            label.textContent = 'Foto dipilih: ' + input.files[0].name;
        } else {
            label.textContent = 'JPG, PNG, WEBP · maksimal 4 MB.';
        }
    };

    input.click();
}
</script>

<?php require ROOT_PATH . '/includes/store_footer.php'; ?>
