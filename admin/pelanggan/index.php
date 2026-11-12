<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$ROOT = '../../'; $A = '../';
require_admin('../login.php');

$q = qs('q');
$hal = max(1, (int)qs('page', '1')); $per = 20;
$w = ''; $p = [];
if ($q !== '') { $like = '%' . addcslashes($q, '%_\\') . '%'; $w = ' WHERE (pl.nama LIKE ? OR pl.email LIKE ? OR pl.no_hp LIKE ?)'; $p = [$like, $like, $like]; }
$total = (int)scalar($pdo, 'SELECT COUNT(*) FROM pelanggan pl' . $w, $p);
$pages = max(1, (int)ceil($total / $per));
$hal = min($hal, $pages);
$st = $pdo->prepare("SELECT pl.*, COUNT(ps.id) AS n_pesanan, COALESCE(SUM(CASE WHEN ps.status IN ('Diproses','Dikirim','Selesai') THEN ps.total_harga END), 0) AS belanja
    FROM pelanggan pl LEFT JOIN pesanan ps ON ps.pelanggan_id = pl.id" . $w . ' GROUP BY pl.id ORDER BY pl.id DESC LIMIT ' . (int)$per . ' OFFSET ' . (int)(($hal - 1) * $per));
$st->execute($p);
$rows = $st->fetchAll();

$adm = ['title' => 'Pelanggan', 'nav' => 'pelanggan'];
require __DIR__ . '/../includes/layout_top.php';
?>
<div class="bar-tools">
  <form method="get">
    <input class="input grow" type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama, email, atau no. HP…">
    <button class="btn btn-ghost btn-sm"><?= icon('search', 16) ?> Cari</button>
    <?php if ($q !== ''): ?><a class="btn btn-ghost btn-sm" href="index.php">Reset</a><?php endif; ?>
  </form>
</div>
<section class="card" style="padding:8px 12px">
<?php if ($rows): ?>
  <div class="scroll-x"><table class="tbl stack-m">
    <thead><tr><th>Pelanggan</th><th>Kontak</th><th>Pesanan</th><th>Total belanja</th><th>Poin</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><div class="rowi" style="padding:0;border:0"><span class="avatar"><?= e(mb_strtoupper(mb_substr($r['nama'], 0, 1))) ?></span><div class="grow"><b><?= e($r['nama']) ?></b><small>Bergabung <?= tgl($r['created_at']) ?></small></div></div></td>
        <td><?= e($r['email']) ?><br><small class="muted"><?= e($r['no_hp'] ?: '—') ?></small></td>
        <td><?= (int)$r['n_pesanan'] ?></td>
        <td><?= rupiah($r['belanja']) ?></td>
        <td><span class="badge b-warn"><?= (int)$r['poin'] ?> poin</span></td>
        <td><div class="acts">
          <a class="btn btn-ghost btn-sm" href="edit.php?id=<?= (int)$r['id'] ?>">Kelola</a>
          <a class="btn btn-ghost btn-sm btn-icon" href="../chat.php?id=<?= (int)$r['id'] ?>" title="Chat" aria-label="Chat dengan <?= e($r['nama']) ?>"><?= icon('chat', 16) ?></a>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <div class="empty" style="border:0"><span class="ic"><?= icon('users', 30) ?></span><h3><?= $q !== '' ? 'Pelanggan tidak ditemukan' : 'Belum ada pelanggan' ?></h3><p><?= $q !== '' ? 'Coba kata kunci lain.' : 'Pelanggan yang mendaftar akan muncul di sini.' ?></p></div>
<?php endif; ?>
</section>
<p class="muted" style="font-size:13px;margin:12px 4px 0"><?= $total ?> pelanggan · halaman <?= $hal ?> dari <?= $pages ?></p>
<?= paginate_links($hal, $pages, $q !== '' ? ['q' => $q] : []) ?>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
