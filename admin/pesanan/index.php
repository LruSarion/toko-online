<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$ROOT = '../../'; $A = '../';
require_admin('../login.php');

$statusList = array_keys(transisi_status());
$q = qs('q'); $status = qs('status'); $dari = qs('dari'); $sampai = qs('sampai');
if (!in_array($status, $statusList, true)) { $status = ''; }
$valid = function ($d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false; };
if (!$valid($dari)) { $dari = ''; } if (!$valid($sampai)) { $sampai = ''; }
$hal = max(1, (int)qs('page', '1')); $per = 20;

$w = []; $p = [];
if ($status !== '') { $w[] = 'status = ?'; $p[] = $status; }
if ($dari !== '') { $w[] = 'tanggal_pesanan >= ?'; $p[] = $dari . ' 00:00:00'; }
if ($sampai !== '') { $w[] = 'tanggal_pesanan <= ?'; $p[] = $sampai . ' 23:59:59'; }
if ($q !== '') {
    if (preg_match('/^TK\d{6}(\d{4,})$/i', $q, $m)) { $w[] = 'id = ?'; $p[] = (int)$m[1]; }
    else {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $w[] = '(nama_pelanggan LIKE ? OR email LIKE ? OR no_hp LIKE ? OR no_resi LIKE ?' . (ctype_digit($q) ? ' OR id = ?' : '') . ')';
        array_push($p, $like, $like, $like, $like);
        if (ctype_digit($q)) { $p[] = (int)$q; }
    }
}
$where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
$total = (int)scalar($pdo, 'SELECT COUNT(*) FROM pesanan' . $where, $p);
$pages = max(1, (int)ceil($total / $per));
$hal = min($hal, $pages);
$st = $pdo->prepare('SELECT * FROM pesanan' . $where . ' ORDER BY id DESC LIMIT ' . (int)$per . ' OFFSET ' . (int)(($hal - 1) * $per));
$st->execute($p);
$rows = $st->fetchAll();

$hitung = [];
foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM pesanan GROUP BY status')->fetchAll() as $r) { $hitung[$r['status']] = (int)$r['n']; }
$semua = array_sum($hitung);
$query = array_filter(['q' => $q, 'status' => $status, 'dari' => $dari, 'sampai' => $sampai], function ($v) { return $v !== ''; });
$tabQ = function (string $s) use ($q, $dari, $sampai) { return '?' . http_build_query(array_filter(['status' => $s, 'q' => $q, 'dari' => $dari, 'sampai' => $sampai], function ($v) { return $v !== ''; })); };
$filterAktif = $q !== '' || $dari !== '' || $sampai !== '';

$adm = ['title' => 'Pesanan', 'nav' => 'pesanan'];
require __DIR__ . '/../includes/layout_top.php';
?>
<div class="tabs">
  <a class="<?= $status === '' ? 'on' : '' ?>" href="<?= e($tabQ('')) ?>">Semua (<?= $semua ?>)</a>
  <?php foreach ($statusList as $s): ?><a class="<?= $status === $s ? 'on' : '' ?>" href="<?= e($tabQ($s)) ?>"><?= e(status_info($s)[1]) ?> (<?= $hitung[$s] ?? 0 ?>)</a><?php endforeach; ?>
</div>

<div class="bar-tools">
  <form method="get">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input class="input grow" type="search" name="q" value="<?= e($q) ?>" placeholder="Cari kode, nama, email, no. HP, atau no. resi…">
    <input class="input" type="date" name="dari" value="<?= e($dari) ?>" aria-label="Dari tanggal" title="Dari tanggal">
    <input class="input" type="date" name="sampai" value="<?= e($sampai) ?>" aria-label="Sampai tanggal" title="Sampai tanggal">
    <button class="btn btn-ghost btn-sm"><?= icon('search', 16) ?> Cari</button>
    <?php if ($filterAktif || $status !== ''): ?><a class="btn btn-ghost btn-sm" href="index.php">Reset</a><?php endif; ?>
  </form>
</div>

<section class="card" style="padding:8px 12px">
<?php if ($rows): ?>
  <div class="scroll-x"><table class="tbl stack-m">
    <thead><tr><th>Pesanan</th><th>Pelanggan</th><th>Total</th><th>Pembayaran</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $o): $kode = kode_pesanan($o['id'], $o['tanggal_pesanan']); ?>
      <tr>
        <td><a href="detail.php?id=<?= (int)$o['id'] ?>"><b><?= e($kode) ?></b></a><br><small class="muted"><?= tgl($o['tanggal_pesanan'], true) ?></small></td>
        <td><?= e($o['nama_pelanggan']) ?><br><small class="muted"><?= e($o['no_hp'] ?: $o['email']) ?></small></td>
        <td><b><?= rupiah($o['total_harga']) ?></b></td>
        <td><?= e($o['metode_bayar'] ?: '—') ?><?php if (!empty($o['bukti_bayar'])): ?><br><small class="plus" style="color:var(--ok)">✓ bukti diunggah</small><?php endif; ?></td>
        <td><?= status_badge($o['status']) ?><?php if (!empty($o['no_resi'])): ?><br><small class="muted">Resi <?= e($o['no_resi']) ?></small><?php endif; ?></td>
        <td><div class="acts"><a class="btn btn-ghost btn-sm" href="detail.php?id=<?= (int)$o['id'] ?>">Kelola</a></div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <div class="empty" style="border:0"><span class="ic"><?= icon('cart', 30) ?></span><h3>Tidak ada pesanan</h3><p><?= ($filterAktif || $status !== '') ? 'Tidak ada pesanan yang cocok dengan filter.' : 'Pesanan dari pelanggan akan muncul di sini.' ?></p></div>
<?php endif; ?>
</section>
<p class="muted" style="font-size:13px;margin:12px 4px 0"><?= $total ?> pesanan · halaman <?= $hal ?> dari <?= $pages ?></p>
<?= paginate_links($hal, $pages, $query) ?>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
