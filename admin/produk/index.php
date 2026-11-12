<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$ROOT = '../../'; $A = '../';
require_admin('../login.php');

$q = qs('q'); $kat = qs('kategori'); $stok = qs('stok'); $tampil = qs('tampil');
$hal = max(1, (int)qs('page', '1')); $per = 15;
$batas = (int)$APP['stok_rendah'];

$w = []; $p = [];
if ($q !== '') { $w[] = '(nama LIKE ? OR deskripsi LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($p, $like, $like); }
if ($kat === '_none') { $w[] = "(kategori IS NULL OR kategori = '')"; } elseif ($kat !== '') { $w[] = 'kategori = ?'; $p[] = $kat; }
if ($stok === 'rendah') { $w[] = 'stok > 0 AND stok <= ?'; $p[] = $batas; } elseif ($stok === 'habis') { $w[] = 'stok = 0'; }
if ($tampil === 'aktif') { $w[] = 'aktif = 1'; } elseif ($tampil === 'nonaktif') { $w[] = 'aktif = 0'; } elseif ($tampil === 'unggulan') { $w[] = 'unggulan = 1'; }
$where = $w ? ' WHERE ' . implode(' AND ', $w) : '';

$total = (int)scalar($pdo, 'SELECT COUNT(*) FROM produk' . $where, $p);
$pages = max(1, (int)ceil($total / $per));
$hal = min($hal, $pages);
$st = $pdo->prepare('SELECT * FROM produk' . $where . ' ORDER BY id DESC LIMIT ' . (int)$per . ' OFFSET ' . (int)(($hal - 1) * $per));
$st->execute($p);
$rows = $st->fetchAll();

$ringkas = [
    'semua'  => (int)scalar($pdo, 'SELECT COUNT(*) FROM produk'),
    'rendah' => (int)scalar($pdo, 'SELECT COUNT(*) FROM produk WHERE stok > 0 AND stok <= ?', [$batas]),
    'habis'  => (int)scalar($pdo, 'SELECT COUNT(*) FROM produk WHERE stok = 0'),
];
$query = array_filter(['q' => $q, 'kategori' => $kat, 'stok' => $stok, 'tampil' => $tampil], function ($v) { return $v !== ''; });
$filterAktif = $query ? true : false;

$adm = ['title' => 'Produk', 'nav' => 'produk'];
require __DIR__ . '/../includes/layout_top.php';
?>
<div class="bar-tools">
  <form method="get">
    <input class="input grow" type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama atau deskripsi produk…">
    <select class="select" name="kategori" data-autosubmit>
      <option value="">Semua kategori</option>
      <?php foreach ($APP['kategori'] as $k): ?><option value="<?= e($k) ?>" <?= $kat === $k ? 'selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?>
      <option value="_none" <?= $kat === '_none' ? 'selected' : '' ?>>Tanpa kategori</option>
    </select>
    <select class="select" name="stok" data-autosubmit>
      <option value="">Semua stok</option>
      <option value="rendah" <?= $stok === 'rendah' ? 'selected' : '' ?>>Stok menipis (<?= $ringkas['rendah'] ?>)</option>
      <option value="habis" <?= $stok === 'habis' ? 'selected' : '' ?>>Stok habis (<?= $ringkas['habis'] ?>)</option>
    </select>
    <select class="select" name="tampil" data-autosubmit>
      <option value="">Semua status</option>
      <option value="aktif" <?= $tampil === 'aktif' ? 'selected' : '' ?>>Aktif</option>
      <option value="nonaktif" <?= $tampil === 'nonaktif' ? 'selected' : '' ?>>Disembunyikan</option>
      <option value="unggulan" <?= $tampil === 'unggulan' ? 'selected' : '' ?>>Unggulan</option>
    </select>
    <button class="btn btn-ghost btn-sm"><?= icon('search', 16) ?> Cari</button>
    <?php if ($filterAktif): ?><a class="btn btn-ghost btn-sm" href="index.php">Reset</a><?php endif; ?>
  </form>
  <a class="btn btn-primary btn-sm" href="tambah.php"><?= icon('plus', 16) ?> Tambah produk</a>
</div>

<section class="card" style="padding:8px 12px">
<?php if ($rows): ?>
  <div class="scroll-x"><table class="tbl stack-m">
    <thead><tr><th>Produk</th><th>Harga</th><th>Stok</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $im = produk_img($r['gambar'], $ROOT, 'thumb'); $s = (int)$r['stok']; ?>
      <tr>
        <td><div class="rowi" style="padding:0;border:0">
          <span class="thumb"><?php if ($im): ?><img src="<?= e($im) ?>" alt="" loading="lazy"><?php else: echo icon('package', 20); endif; ?></span>
          <div class="grow"><b><?= e($r['nama']) ?></b><small><?= e($r['kategori'] ?: 'Tanpa kategori') ?> · #<?= (int)$r['id'] ?></small></div>
        </div></td>
        <td><b><?= rupiah($r['harga']) ?></b><?php if (!empty($r['harga_coret'])): ?><br><small class="muted" style="text-decoration:line-through"><?= rupiah($r['harga_coret']) ?></small><?php endif; ?></td>
        <td><?= $s === 0 ? '<span class="badge b-bad">Habis</span>' : ($s <= $batas ? '<span class="badge b-warn">' . $s . ' (menipis)</span>' : '<b>' . $s . '</b>') ?></td>
        <td>
          <?= (int)$r['aktif'] ? '<span class="badge b-ok">Aktif</span>' : '<span class="badge b-muted">Disembunyikan</span>' ?>
          <?php if ((int)$r['unggulan']): ?><span class="badge b-violet">Unggulan</span><?php endif; ?>
        </td>
        <td><div class="acts">
          <?php if ((int)$r['aktif']): ?><a class="btn btn-ghost btn-sm btn-icon" href="<?= $ROOT ?>public/product.php?id=<?= (int)$r['id'] ?>" target="_blank" rel="noopener" title="Lihat di toko" aria-label="Lihat di toko"><?= icon('eye', 16) ?></a><?php endif; ?>
          <a class="btn btn-ghost btn-sm btn-icon" href="edit.php?id=<?= (int)$r['id'] ?>" title="Edit" aria-label="Edit <?= e($r['nama']) ?>"><?= icon('edit', 16) ?></a>
          <form method="post" action="hapus.php" data-confirm="Hapus “<?= e($r['nama']) ?>”? Produk yang pernah dipesan hanya disembunyikan.">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-danger btn-sm btn-icon" title="Hapus" aria-label="Hapus <?= e($r['nama']) ?>"><?= icon('trash', 16) ?></button>
          </form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <div class="empty" style="border:0"><span class="ic"><?= icon('package', 30) ?></span><h3><?= $filterAktif ? 'Tidak ada produk yang cocok' : 'Belum ada produk' ?></h3><p><?= $filterAktif ? 'Coba ubah atau reset filter.' : 'Tambahkan produk pertamamu.' ?></p><a class="btn btn-primary" href="<?= $filterAktif ? 'index.php' : 'tambah.php' ?>"><?= $filterAktif ? 'Reset filter' : 'Tambah produk' ?></a></div>
<?php endif; ?>
</section>
<p class="muted" style="font-size:13px;margin:12px 4px 0"><?= $total ?> produk<?= $filterAktif ? ' sesuai filter' : '' ?> · halaman <?= $hal ?> dari <?= $pages ?></p>
<?= paginate_links($hal, $pages, $query) ?>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
